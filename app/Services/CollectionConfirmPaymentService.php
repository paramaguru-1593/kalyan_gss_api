<?php

namespace App\Services;

use App\Exceptions\ThirdPartyApiException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Equals / MyKalyan: GET Collection_tbs/confirmPayment after a successful gateway capture.
 */
class CollectionConfirmPaymentService
{
    private const CONFIG_KEY = 'thirdparty.mykalyan';

    public function __construct(
        private readonly ThirdPartyAuthService $authService,
    ) {
    }

    /**
     * @param  array{Date: string, enrNo: string, amount: string, transId: string, email: string, channel: string}  $params
     * @return array{
     *     success: bool,
     *     duplicate: bool,
     *     receipt_id: ?string,
     *     message: ?string,
     *     raw: ?array
     * }
     *
     * @throws ThirdPartyApiException
     */
    public function confirmPayment(array $params): array
    {
        foreach (['Date', 'enrNo', 'amount', 'transId', 'email', 'channel'] as $key) {
            if (! isset($params[$key]) || $params[$key] === '') {
                throw new \InvalidArgumentException("Missing or empty required field: {$key}");
            }
        }

        $path = ltrim((string) config(self::CONFIG_KEY . '.confirm_collection_payment_path', 'thirdparty/api/Collection_tbs/confirmPayment'), '/');
        $baseUrl = rtrim((string) config(self::CONFIG_KEY . '.base_url', ''), '/');
        $url = $baseUrl . '/' . $path;

        $token = $this->authService->getValidToken();

        $query = [
            'access_token' => $token,
            'Date' => mb_substr((string) $params['Date'], 0, 50),
            'enrNo' => mb_substr((string) $params['enrNo'], 0, 50),
            'amount' => mb_substr((string) $params['amount'], 0, 50),
            'transId' => mb_substr((string) $params['transId'], 0, 50),
            'email' => mb_substr((string) $params['email'], 0, 100),
            'channel' => mb_substr((string) $params['channel'], 0, 50),
        ];

        $response = Http::timeout(30)
            ->acceptJson()
            ->get($url, $query);

        $json = $response->json();
        if (! is_array($json)) {
            Log::warning('Collection confirmPayment: invalid JSON body', [
                'url' => $url,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'duplicate' => false,
                'receipt_id' => null,
                'message' => 'Invalid response from collection API',
                'raw' => null,
            ];
        }

        $errorBlock = $json['error'] ?? [];
        $status = isset($errorBlock['status']) ? (int) $errorBlock['status'] : $response->status();

        if ($status === 200 && isset($json['data']) && is_array($json['data']) && $json['data'] !== []) {
            $first = $json['data'][0] ?? [];
            if (is_array($first)) {
                $receiptId = $first['ReceiptID'] ?? $first['receiptId'] ?? null;
                if ($receiptId !== null && $receiptId !== '') {
                    return [
                        'success' => true,
                        'duplicate' => false,
                        'receipt_id' => (string) $receiptId,
                        'message' => null,
                        'raw' => $json,
                    ];
                }
            }
        }

        $msg = (string) ($errorBlock['message'] ?? '');
        $desc = (string) ($errorBlock['description'] ?? '');
        $combined = $msg . ' ' . $desc;

        if ($status === 400 && stripos($combined, 'already exists') !== false) {
            return [
                'success' => false,
                'duplicate' => true,
                'receipt_id' => null,
                'message' => $msg !== '' ? $msg : 'TransactionID already exists in the Collection Table',
                'raw' => $json,
            ];
        }

        if (! $response->successful()) {
            Log::warning('Collection confirmPayment: HTTP error', [
                'url' => $url,
                'http_status' => $response->status(),
                'body' => $json,
            ]);
        }

        return [
            'success' => false,
            'duplicate' => false,
            'receipt_id' => null,
            'message' => $msg !== '' ? $msg : 'Collection confirmation failed',
            'raw' => $json,
        ];
    }
}
