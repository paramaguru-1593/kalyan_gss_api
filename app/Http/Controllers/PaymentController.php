<?php

namespace App\Http\Controllers;

use App\Exceptions\ThirdPartyApiException;
use App\Services\CollectionConfirmPaymentService;
use App\Services\ThirdPartyApiService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    public function __construct(
        private readonly ThirdPartyApiService $thirdPartyApi,
        private readonly CollectionConfirmPaymentService $collectionConfirm,
    ) {
    }

    /**
     * Get Payment Information – whether payment can be accepted for this enrollment/month.
     * Proxies to MyKalyan: GET .../thirdparty/api/Enrollment_tbs/getPaymentInformation?access_token=...&EnrollmentID=...
     */
    public function getPaymentInformation(Request $request): JsonResponse
    {
        $request->validate([
            'EnrollmentID' => 'required|string|max:50',
        ], [
            'EnrollmentID.required' => 'EnrollmentID is required',
        ]);

        $enrollmentId = trim((string) $request->query('EnrollmentID'));
        $path = (string) config(
            'thirdparty.mykalyan.get_payment_information_path',
            'thirdparty/api/Enrollment_tbs/getPaymentInformation'
        );
        $path = ltrim($path, '/');

        try {
            $response = $this->thirdPartyApi->getWithAccessTokenInQuery($path, [
                'EnrollmentID' => $enrollmentId,
            ]);

            return response()->json($response);
        } catch (ThirdPartyApiException $e) {
            $status = $e->getHttpStatus() ?: 502;
            $body = $e->getResponseBody() ?? [];

            if (isset($body['data'], $body['error'])) {
                return response()->json($body, $status >= 400 ? $status : 200);
            }

            if (isset($body['message'], $body['status'])) {
                $errorStatus = (int) $body['status'];

                return response()->json($body, $errorStatus > 0 ? $errorStatus : $status);
            }

            $error = $body['error'] ?? [
                'status' => $status,
                'message' => $e->getMessage(),
                'description' => '',
            ];

            return response()->json([
                'data' => $body['data'] ?? (object) [],
                'error' => $error,
            ], $status >= 400 ? $status : 200);
        }
    }

    /**
     * Collection Creation – confirm payment and get receipt after gateway success.
     * GET /api/Collection_tbs/confirmPayment
     * Query: Date, enrNo, amount, transId, email, channel (all required). Header: access_token (optional).
     */
    public function confirmPayment(Request $request): JsonResponse
    {
        $request->validate([
            'Date' => 'required|string|max:50',
            'enrNo' => 'required|string|max:50',
            'amount' => 'required|string|max:50',
            'transId' => 'required|string|max:50',
            'email' => 'required|string|max:100',
            'channel' => 'required|string|max:50',
        ]);

        $params = [
            'Date' => (string) $request->query('Date'),
            'enrNo' => (string) $request->query('enrNo'),
            'amount' => (string) $request->query('amount'),
            'transId' => (string) $request->query('transId'),
            'email' => (string) $request->query('email'),
            'channel' => (string) $request->query('channel'),
        ];

        try {
            $result = $this->collectionConfirm->confirmPayment($params);
        } catch (ThirdPartyApiException $e) {
            $status = $e->getHttpStatus() ?: 502;
            $body = $e->getResponseBody() ?? [];

            return response()->json([
                'data' => $body['data'] ?? (object) [],
                'error' => $body['error'] ?? [
                    'status' => $status,
                    'message' => $e->getMessage(),
                    'description' => '',
                ],
            ], $status >= 400 && $status < 600 ? $status : 502);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'data' => (object) [],
                'error' => [
                    'status' => 422,
                    'message' => $e->getMessage(),
                    'description' => '',
                ],
            ], 422);
        }

        if (($result['duplicate'] ?? false) === true) {
            $raw = $result['raw'] ?? [];
            $err = is_array($raw) ? ($raw['error'] ?? []) : [];

            return response()->json([
                'data' => is_array($raw['data'] ?? null) ? $raw['data'] : (object) [],
                'error' => [
                    'status' => 400,
                    'message' => (string) ($err['message'] ?? 'TransactionID already exists in the Collection Table'),
                    'description' => (string) ($err['description'] ?? 'OrderID already exists in the Collection Table'),
                ],
            ], 400);
        }

        if (($result['success'] ?? false) && ($result['receipt_id'] ?? '') !== '') {
            return response()->json([
                'data' => [['ReceiptID' => $result['receipt_id']]],
                'error' => [
                    'status' => 200,
                    'message' => 'Success',
                    'description' => '',
                ],
            ]);
        }

        $raw = $result['raw'] ?? [];
        $err = is_array($raw) ? ($raw['error'] ?? []) : [];

        return response()->json([
            'data' => isset($raw['data']) ? $raw['data'] : (object) [],
            'error' => [
                'status' => isset($err['status']) ? (int) $err['status'] : 400,
                'message' => (string) ($err['message'] ?? ($result['message'] ?? 'Collection confirmation failed')),
                'description' => (string) ($err['description'] ?? ''),
            ],
        ], 400);
    }
}
