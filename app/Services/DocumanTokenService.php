<?php

namespace App\Services;

use App\Exceptions\DocumanApiException;
use App\Models\DocumanAccessToken;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Manages Docman India API access tokens: get valid token, refresh via login API.
 * Uses row-level locking (lockForUpdate) to prevent multiple simultaneous refresh calls.
 * expires_at is always set to now + 1 day when token is generated.
 */
class DocumanTokenService
{
    /**
     * Returns a valid access token for the given name. If no record exists or token
     * is expired / within 5 minutes of expiry, refreshes inside a DB transaction with
     * row lock. Otherwise returns existing token without calling the API.
     *
     * @throws DocumanApiException
     */
    public function getValidToken(string $name): string
    {
        $name = $this->normalizeName($name);

        return DB::transaction(function () use ($name) {
            $token = DocumanAccessToken::where('name', $name)->lockForUpdate()->first();

            if ($this->isTokenValid($token)) {
                return $token->access_token;
            }

            $this->refreshTokenWithinTransaction($name);
            $refreshed = DocumanAccessToken::where('name', $name)->first();

            if (! $refreshed || ! $refreshed->access_token) {
                throw new DocumanApiException('Failed to obtain token after refresh.');
            }

            return $refreshed->access_token;
        });
    }

    /**
     * Calls Docman Login API and creates or updates the token record. expires_at is
     * set to now + 1 day. Should be called within a transaction that holds the row
     * lock for the given name to avoid race conditions.
     *
     * @throws DocumanApiException
     */
    public function refreshToken(string $name): void
    {
        $name = $this->normalizeName($name);

        DB::transaction(function () use ($name) {
            DocumanAccessToken::where('name', $name)->lockForUpdate()->first();
            $this->refreshTokenWithinTransaction($name);
        });
    }

    /**
     * Call login API and upsert token. Caller must hold transaction and lock for name.
     */
    private function refreshTokenWithinTransaction(string $name): void
    {
        $baseUrl = rtrim(config('documan.base_url', ''), '/');
        $path = ltrim(config('documan.token_path', 'token'), '/');
        $url = $baseUrl !== '' ? $baseUrl . '/' . $path : $path;
        $username = config('documan.username', '');
        $password = config('documan.password', '');

        $missing = [];
        if ($baseUrl === '') {
            $missing[] = 'DOCUMAN_BASE_URL';
        }
        if ($username === '') {
            $missing[] = 'DOCUMAN_USERNAME';
        }
        if ($password === '') {
            $missing[] = 'DOCUMAN_PASSWORD';
        }
        if ($missing !== []) {
            throw new DocumanApiException(
                'Docman API not configured. Set in .env: ' . implode(', ', $missing)
            );
        }

        $response = Http::timeout(config('documan.http_timeout_seconds', 15))
            ->acceptJson()
            ->asForm()
            ->post($url, [
                'grant_type' => 'password',
                'username' => $username,
                'password' => $password,
            ]);

        $body = $response->json() ?? [];
        $status = $response->status();

        if (! $response->successful()) {
            Log::warning('Docman login API failed', [
                'name' => $name,
                'url' => $url,
                'status' => $status,
                'body' => $body,
            ]);
            throw new DocumanApiException(
                $body['error_description'] ?? $body['error'] ?? 'Docman login failed',
                $status,
                null,
                $body,
                $status
            );
        }

        $accessToken = $body['access_token'] ?? null;
        if (empty($accessToken) || ! is_string($accessToken)) {
            Log::warning('Docman login response missing access_token', [
                'name' => $name,
                'body' => $body,
            ]);
            throw new DocumanApiException('Invalid login response: missing access_token.', $status, null, $body, $status);
        }

        $ttlDays = config('documan.token_ttl_days', 1);
        $expiresAt = Carbon::now()->addDays($ttlDays);

        DocumanAccessToken::updateOrCreate(
            ['name' => $name],
            [
                'access_token' => $accessToken,
                'token_type' => $body['token_type'] ?? 'bearer',
                'expires_in' => isset($body['expires_in']) ? (int) $body['expires_in'] : null,
                'user_name' => $body['userName'] ?? null,
                'expires_at' => $expiresAt,
                'updated_at' => now(),
            ]
        );
    }

    /**
     * Token is valid if it exists, has access_token, and is not within 5 minutes of expiry.
     * Requirement: refresh when now() >= expires_at->subMinutes(5).
     */
    private function isTokenValid(?DocumanAccessToken $token): bool
    {
        if (! $token || ! $token->access_token || ! $token->expires_at) {
            return false;
        }

        $bufferMinutes = config('documan.refresh_buffer_minutes', 5);
        $threshold = $token->expires_at->copy()->subMinutes($bufferMinutes);

        return Carbon::now()->lt($threshold);
    }

    private function normalizeName(string $name): string
    {
        return trim($name);
    }
}
