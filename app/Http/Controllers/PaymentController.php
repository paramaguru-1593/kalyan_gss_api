<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    /**
     * Get Payment Information – whether payment can be accepted for this enrollment/month.
     * GET /api/Enrollment_tbs/getPaymentInformation
     * Query: EnrollmentID (required). Header: access_token (optional, no validation).
     */
    public function getPaymentInformation(Request $request): JsonResponse
    {
        $request->validate([
            'EnrollmentID' => 'required|string|max:50',
        ], [
            'EnrollmentID.required' => 'EnrollmentID is required',
        ]);

        $enrollmentId = $request->query('EnrollmentID');
        $result = $this->fetchPaymentInformation($enrollmentId);

        if ($result === null) {
            return response()->json([
                'data' => [
                    'paymentAccepted' => false,
                    'paymentAcceptedMonth' => null,
                    'acceptanceReason' => 'invalid_account',
                ],
                'error' => [
                    'status' => 10000,
                    'message' => 'No Data Found',
                    'description' => 'Failed',
                ],
            ]);
        }

        return response()->json([
            'data' => $result,
            'error' => [
                'status' => 200,
                'message' => 'success',
                'description' => '',
            ],
        ]);
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

        $transId = $request->query('transId');
        $result = $this->createCollection([
            'Date' => $request->query('Date'),
            'enrNo' => $request->query('enrNo'),
            'amount' => $request->query('amount'),
            'transId' => $transId,
            'email' => $request->query('email'),
            'channel' => $request->query('channel'),
        ]);

        if ($result === null) {
            return response()->json([
                'data' => (object) [],
                'error' => [
                    'status' => 400,
                    'message' => 'TransactionID already exists in the Collection Table',
                    'description' => 'OrderID already exists in the Collection Table',
                ],
            ], 400);
        }

        return response()->json([
            'data' => [['ReceiptID' => $result]],
            'error' => [
                'status' => 200,
                'message' => 'Success',
                'description' => '',
            ],
        ]);
    }

    /**
     * Fetch whether payment is accepted for this enrollment. Return null for invalid account.
     */
    private function fetchPaymentInformation(string $enrollmentId): ?array
    {
        $enrollmentId = trim($enrollmentId);
        if ($enrollmentId === '') {
            return null;
        }

        // TODO: Check max payment limit for current month (DB/external). Return null if invalid account.
        return [
            'paymentAccepted' => true,
            'paymentAcceptedMonth' => 'February-2025',
            'acceptanceReason' => 'success',
        ];
    }

    /**
     * Create collection record and return ReceiptID. Return null if transId already exists.
     */
    private function createCollection(array $input): ?int
    {
        // TODO: Check transId uniqueness in DB; insert collection; return new ReceiptID. Return null if duplicate.
        return 16331715166203;
    }
}
