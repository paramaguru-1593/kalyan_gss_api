<?php

namespace App\Services;

use App\Exceptions\DocumanApiException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Docman India API client. Uses DocumanTokenService for Bearer auth.
 */
class DocumanApiService
{
    public function __construct(
        private readonly DocumanTokenService $tokenService
    ) {
    }

    /**
     * Get customer details by MobileNo and optionally DocumentType + DocumentNumber.
     *
     * @param  array{MobileNo?: string, DocumentType?: string, DocumentNumber?: string}  $body
     * @return array{StatusCode: int, Message: string, Data: array}
     *
     * @throws DocumanApiException
     */
    public function getCustomerDetails(array $body): array
    {
        $body = array_filter($body, fn ($v) => $v !== null && $v !== '');
        $token = $this->tokenService->getValidToken(config('documan.default_token_name', 'default'));
        $baseUrl = rtrim(config('documan.base_url', ''), '/');
        $url = $baseUrl . '/api/customer/GetCustomerDetails';

        $response = Http::timeout(config('documan.http_timeout_seconds', 15))
            ->acceptJson()
            ->withToken($token)
            ->post($url, $body);

        if (! $response->successful()) {
            Log::warning('Docman GetCustomerDetails failed', [
                'url' => $url,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);
            throw new DocumanApiException(
                $response->json('Message') ?? 'Docman GetCustomerDetails failed',
                $response->status(),
                null,
                $response->json(),
                $response->status()
            );
        }

        $data = $response->json();
        if (! is_array($data)) {
            throw new DocumanApiException('Invalid GetCustomerDetails response.', 502, null, null, 502);
        }

        return $data;
    }
}
