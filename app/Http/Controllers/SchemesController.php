<?php

namespace App\Http\Controllers;

use App\Exceptions\ThirdPartyApiException;
use App\Services\GetCustomerLedgerReportSyncService;
use App\Services\GetSchemesByMobileNumberSyncService;
use App\Services\SchemeSyncService;
use App\Services\ThirdPartyApiService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class SchemesController extends Controller
{
    /** Third-party path: GET with access_token in query (MyKalyan externals). */
    private const THIRDPARTY_GET_SCHEMES_PATH = 'thirdparty/api/externals/getSchemesByMobileNumber';
    private const THIRDPARTY_GET_ACCOUNT_INFO_PATH = 'thirdparty/api/Enrollment_tbs/getAccountInformation';
    private const THIRDPARTY_STOREBASED_SCHEME_PATH = 'thirdparty/api/storebasedscheme_data';
    private const THIRDPARTY_GET_CUSTOMER_LEDGER_REPORT_PATH = 'thirdparty/api/externals/getCustomerLedgerReport';

    public function __construct(
        private readonly ThirdPartyApiService $thirdPartyApi,
        private readonly SchemeSyncService $schemeSync,
        private readonly GetSchemesByMobileNumberSyncService $getSchemesSync,
        private readonly GetCustomerLedgerReportSyncService $ledgerReportSync
    ) {
    }

    /**
     * Get scheme information by mobile number.
     * Always fetches from third-party API, syncs to DB, and returns third-party response.
     * GET /api/externals/getSchemesByMobileNumber?MobileNumber=...
     */
    public function getSchemesByMobileNumber(Request $request): JsonResponse
    {
        $request->validate([
            'MobileNumber' => 'required|string|max:50',
        ], [
            'MobileNumber.required' => 'MobileNumber is required',
        ]);

        $mobileNumber = $this->normalizeMobile((string) $request->query('MobileNumber'));

        try {
            $response = $this->thirdPartyApi->getWithAccessTokenInQuery(self::THIRDPARTY_GET_SCHEMES_PATH, [
                'MobileNumber' => $mobileNumber,
            ]);

            try {
                $this->schemeSync->syncFromMobileNumberResponse(SchemeSyncService::DEFAULT_STORE_ID, $response);
            } catch (\Throwable $e) {
                Log::warning('Scheme sync from getSchemesByMobileNumber failed', [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }

            try {
                $this->getSchemesSync->syncFromResponse($response);
            } catch (\Throwable $e) {
                Log::warning('Customer/enrollment sync from getSchemesByMobileNumber failed', [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }

            return response()->json($response);
        } catch (ThirdPartyApiException $e) {
            $status = $e->getHttpStatus() ?: 502;
            $body = $e->getResponseBody();
            $error = $body['error'] ?? [
                'status' => $status,
                'message' => $e->getMessage(),
                'description' => '',
            ];
            return response()->json([
                'data' => (object) [],
                'error' => $error,
            ], $status >= 400 ? $status : 200);
        }
    }

    private function normalizeMobile(string $value): string
    {
        return trim(preg_replace('/\s+/', '', $value));
    }

    /**
     * Get scheme information by account number (EnrollmentID).
     * GET /api/Enrollment_tbs/getAccountInformation?EnrollmentID=...
     * Proxies to third-party: .../thirdparty/api/Enrollment_tbs/getAccountInformation?access_token=...&EnrollmentID=...
     */
    public function getAccountInformation(Request $request): JsonResponse
    {
        $request->validate([
            'EnrollmentID' => 'required|string|max:50',
        ], [
            'EnrollmentID.required' => 'EnrollmentID is required',
        ]);

        $enrollmentId = $request->query('EnrollmentID');

        try {
            $response = $this->thirdPartyApi->getWithAccessTokenInQuery(self::THIRDPARTY_GET_ACCOUNT_INFO_PATH, [
                'EnrollmentID' => $enrollmentId,
            ]);
        } catch (ThirdPartyApiException $e) {
            $status = $e->getHttpStatus() ?: 502;
            $body = $e->getResponseBody();
            $error = $body['error'] ?? [
                'status' => $status,
                'message' => $e->getMessage(),
                'description' => '',
            ];
            return response()->json([
                'data' => $body['data'] ?? [],
                'error' => $error,
            ], $status >= 400 ? $status : 200);
        }

        return response()->json($response);
    }

    /**
     * Get Scheme List – available schemes for enrolment (installments, min/max EMI). Default store_id is 3.
     * POST /api/storebasedscheme_data
     * Proxies to third-party with Bearer token in header, syncs into schemes table,
     * and returns the third-party response.
     */
    public function storeBasedSchemeData(Request $request): JsonResponse
    {
        $storeId = $request->input('store_id', 3);
        if (! is_numeric($storeId) || (int) $storeId <= 0) {
            return response()->json([
                'error' => [
                    'status' => 400,
                    'message' => 'Invalid Store ID !!',
                ],
            ], 400);
        }

        $storeId = (int) $storeId;

        try {
            $response = $this->thirdPartyApi->post(self::THIRDPARTY_STOREBASED_SCHEME_PATH, [
                'store_id' => $storeId,
            ]);
            $this->schemeSync->syncFromStoreBasedResponse($storeId, $response);
        } catch (ThirdPartyApiException $e) {
            $status = $e->getHttpStatus() ?: 502;
            $body = $e->getResponseBody();
            $error = $body['error'] ?? [
                'status' => $status,
                'message' => $e->getMessage(),
            ];
            return response()->json([
                'error' => $error,
            ], $status >= 400 ? $status : 200);
        }

        return response()->json($response);
    }

    /**
     * Get Customer Ledger – transaction history and financial info by enrollment number.
     * Always fetches from third-party API, syncs to DB, and returns third-party response.
     * GET /api/externals/getCustomerLedgerReport?EnrollmentNo=...
     */
    public function getCustomerLedgerReport(Request $request): JsonResponse
    {
        $request->validate([
            'EnrollmentNo' => 'required|string|max:50',
        ], [
            'EnrollmentNo.required' => 'EnrollmentNo is required',
        ]);

        $enrollmentNo = trim((string) $request->query('EnrollmentNo'));

        try {
            $response = $this->thirdPartyApi->getWithAccessTokenInQuery(self::THIRDPARTY_GET_CUSTOMER_LEDGER_REPORT_PATH, [
                'EnrollmentNo' => $enrollmentNo,
            ]);

            try {
                $this->ledgerReportSync->syncFromResponse($response);
            } catch (\Throwable $e) {
                Log::warning('Customer ledger sync from getCustomerLedgerReport failed', [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }

            return response()->json($response);
        } catch (ThirdPartyApiException $e) {
            $status = $e->getHttpStatus() ?: 502;
            $body = $e->getResponseBody();
            $error = $body['error'] ?? [
                'status' => $status,
                'message' => $e->getMessage(),
                'description' => '',
            ];
            return response()->json([
                'data' => (object) [],
                'error' => $error,
            ], $status >= 400 ? $status : 200);
        }
    }
}
