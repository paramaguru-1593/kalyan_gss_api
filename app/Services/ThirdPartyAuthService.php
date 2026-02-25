<?php

namespace App\Services;

use App\Exceptions\ThirdPartyApiException;
use App\Models\ThirdPartyToken;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Manages third-party API authentication: login, token storage, and refresh.
 * Uses a cache lock to prevent multiple simultaneous refresh requests.
 */
class ThirdPartyAuthService
{
    private const CONFIG_KEY = 'thirdparty.mykalyan';

    public function __construct(
        private readonly string $tokenName
    ) {
    }

    /**
     * Returns a valid access token. Refreshes if missing, expired, or expiring within buffer.
     *
     * @throws ThirdPartyApiException
     */
    public function getValidToken(): string
    {
        $token = $this->getStoredToken();

        if ($this->isTokenValid($token)) {
            return $token->getAttribute('access_token');
        }

        $lockKey = 'third_party_token_refresh:' . $this->tokenName;
        $lockSeconds = config(self::CONFIG_KEY . '.lock_seconds', 30);
        $lock = Cache::lock($lockKey, $lockSeconds);

        if ($lock->block($lockSeconds)) {
            try {
                $token = $this->getStoredToken();
                if ($this->isTokenValid($token)) {
                    return $token->getAttribute('access_token');
                }
                $this->refreshToken();
                $token = $this->getStoredToken();
                if (! $token) {
                    throw new ThirdPartyApiException('Failed to obtain token after refresh.');
                }
                return $token->getAttribute('access_token');
            } finally {
                $lock->release();
            }
        }

        $token = $this->getStoredToken();
        if ($token && $token->getAttribute('access_token')) {
            return $token->getAttribute('access_token');
        }

        throw new ThirdPartyApiException('Could not acquire token (lock timeout).');
    }

    /**
     * Calls third-party login API and persists token + expiry.
     *
     * @throws ThirdPartyApiException
     */
    public function refreshToken(): void
    {
        $baseUrl = rtrim(config(self::CONFIG_KEY . '.base_url', ''), '/');
        $path = ltrim(config(self::CONFIG_KEY . '.login_path', ''), '/');
        $url = $baseUrl . '/' . $path;
        $username = config(self::CONFIG_KEY . '.username');
        $password = config(self::CONFIG_KEY . '.password');

        $missing = [];
        if ($baseUrl === '') {
            $missing[] = 'THIRDPARTY_BASE_URL';
        }
        if ($path === '') {
            $missing[] = 'THIRDPARTY_LOGIN_PATH';
        }
        if ($username === '') {
            $missing[] = 'THIRDPARTY_USERNAME';
        }
        if ($password === '') {
            $missing[] = 'THIRDPARTY_PASSWORD';
        }
        if ($missing !== []) {
            throw new ThirdPartyApiException(
                'Third-party API not configured. Set in .env: ' . implode(', ', $missing)
            );
        }

        $response = Http::timeout(15)
            ->acceptJson()
            ->post($url, [
                'username' => $username,
                'password' => $password,
            ]);

        $body = $response->json();
        $status = $response->status();

        if (! $response->successful()) {
            Log::warning('Third-party login failed', [
                'url' => $url,
                'status' => $status,
                'body' => $body,
            ]);
            throw new ThirdPartyApiException(
                $body['error']['message'] ?? 'Third-party login failed',
                $status,
                null,
                $body,
                $status
            );
        }

        $data = $body['data'] ?? null;
        if (empty($data['id'])) {
            Log::warning('Third-party login response missing token', ['body' => $body]);
            throw new ThirdPartyApiException('Invalid login response: missing token.', $status, null, $body, $status);
        }

        $created = $data['created'] ?? null;
        $ttl = (int) ($data['ttl'] ?? 0);
        $userId = isset($data['userId']) ? (int) $data['userId'] : null;

        // TTL may be in seconds or milliseconds; expires_at from response created + ttl
        if ($ttl > 100000000) {
            $ttl = (int) floor($ttl / 1000);
        }
        $expiresAt = $created && $ttl > 0
            ? Carbon::parse($created)->addSeconds($ttl)
            : now()->addSeconds($ttl ?: 1800);

        ThirdPartyToken::updateOrCreate(
            ['name' => $this->tokenName],
            [
                'access_token' => $data['id'],
                'expires_at' => $expiresAt,
                'user_id' => $userId,
                'updated_at' => now(),
            ]
        );
    }

    private function getStoredToken(): ?ThirdPartyToken
    {
        return ThirdPartyToken::where('name', $this->tokenName)->first();
    }

    // private function isTokenValid(?ThirdPartyToken $token): bool
    // {
    //     if (! $token || ! $token->getAttribute('access_token')) {
    //         return false;
    //     }
    //     $bufferSeconds = config(self::CONFIG_KEY . '.buffer_seconds', 600);
    //     return $token->expires_at->subSeconds($bufferSeconds)->isFuture();
    // }

    // private function isTokenValid(?ThirdPartyToken $token): bool
    // {
    //     if (! $token || ! $token->getAttribute('access_token') || ! $token->expires_at) {
    //         return false;
    //     }

    //     $bufferSeconds = config(self::CONFIG_KEY . '.buffer_seconds', 300); // 5 minutes

    //     // Refresh if expires_at <= now + buffer
    //     return $token->expires_at->greaterThan(now()->addSeconds($bufferSeconds));
    // }

    private function isTokenValid(?ThirdPartyToken $token): bool
    {
        if (! $token || ! $token->access_token || ! $token->expires_at) {
            \Log::info('Token invalid: missing data');
            return false;
        }

        $bufferSeconds = config('thirdparty.mykalyan.buffer_seconds', 300);

        $now = now();
        $nowPlusBuffer = now()->addSeconds($bufferSeconds);
        $expiresAt = $token->expires_at;

        $result = $expiresAt->greaterThan($nowPlusBuffer);

        \Log::info('TOKEN VALIDATION DEBUG', [
            'expires_at' => $expiresAt,
            'now' => $now,
            'now_plus_buffer' => $nowPlusBuffer,
            'comparison_result' => $result,
        ]);

        return $result;
    }
}
