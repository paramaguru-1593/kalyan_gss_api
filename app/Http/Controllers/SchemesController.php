<?php

namespace App\Http\Controllers;

use App\Exceptions\ThirdPartyApiException;
use App\Models\Customer;
use App\Models\Scheme;
use App\Models\SchemeEnrollment;
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
    private const THIRDPARTY_GET_CUSTOMER_LEDGER_PATH = 'thirdparty/api/externals/getCustomerLedgerReport';
    private const THIRDPARTY_GET_ACCOUNT_INFO_PATH = 'thirdparty/api/Enrollment_tbs/getAccountInformation';
    private const THIRDPARTY_STOREBASED_SCHEME_PATH = 'thirdparty/api/storebasedscheme_data';

    public function __construct(
        private readonly ThirdPartyApiService $thirdPartyApi,
        private readonly SchemeSyncService $schemeSync,
        private readonly GetSchemesByMobileNumberSyncService $getSchemesSync
    ) {
    }

    /**
     * Get scheme information by mobile number.
     * Returns data from local customers + scheme_enrollments tables.
     * If no local data exists, fetches from third-party API, syncs to DB, then returns from DB.
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

        $customer = Customer::where('mobile_no', $mobileNumber)->with('schemeEnrollments')->first();

        if (! $customer) {
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
                $customer = Customer::where('mobile_no', $mobileNumber)->with('schemeEnrollments')->first();
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

        if (! $customer) {
            return response()->json([
                'data' => (object) [],
                'error' => [
                    'status' => 10000,
                    'message' => 'No Data Found',
                    'description' => 'Failed',
                ],
            ], 200);
        }

        return response()->json($this->buildGetSchemesResponseFromCustomer($customer));
    }

    /**
     * Build response matching the API doc structure:
     * data.Response.data = { customerId, profile, enrollmentList, IDProofStatus, IDProofType, IDProofURL, IDProofNumber }
     */
    private function buildGetSchemesResponseFromCustomer(Customer $customer): array
    {
        $enrollmentList = $customer->schemeEnrollments->map(function (SchemeEnrollment $e) use ($customer) {
            $finalRedeemable = $e->pending_amount !== null
                ? (float) $e->pending_amount + (float) $e->paid_amount
                : null;
            return [
                'PlanType' => $e->scheme_name,
                'SchemeName' => $e->scheme_name,
                'NomineeFirstName' => $customer->nominee_name ? explode(' ', $customer->nominee_name)[0] ?? $customer->nominee_name : '',
                'NomineeLastName' => $customer->nominee_name ? (explode(' ', $customer->nominee_name)[1] ?? '') : '',
                'NomineeRelationship' => $customer->relation_of_nominee ?? '',
                'NomineeMobileNumber' => $customer->nominee_mobile_number ?? '',
                'NomineeAddress' => $customer->nominee_address ?? '',
                'NomineeEmailAddress' => '',
                'Status' => $e->status ?? '',
                'Active' => true,
                'CustomerID' => $customer->customerId,
                'JoinDate' => $e->enrollment_date ? $e->enrollment_date->format('Y-m-d H:i:s') : null,
                'EndDate' => $e->maturity_date ? $e->maturity_date->format('Y-m-d') : null,
                'NoMonths' => null,
                'InitialMOP' => '',
                'EMIAmount' => (float) $e->installment_amount,
                'EnrollmentDayGoldRate' => null,
                'EnrollmentID' => $e->enrollment_id,
                'SchemeID' => $e->scheme_id,
                'FeeAmount' => 0,
                'IsMembershipFeeRequired' => '',
                'FinalRedeemableAmount' => $finalRedeemable,
                'SchemeEfficientType' => '',
                'ReasonForInEfficient' => '',
                'SIONCC' => false,
                'Emandate' => false,
                'TransactionId' => '',
                'DebitDate' => '',
                'TotalPaidAmount' => (float) $e->paid_amount,
                'collections' => [],
            ];
        })->values()->all();

        $profile = [
            'personalDetails' => [
                'FirstName' => $customer->first_name ?? '',
                'LastName' => $customer->last_name ?? '',
                'MobileNumber' => $customer->mobile_no ?? '',
                'EmailAddress' => $customer->email ?? '',
            ],
            'currentAddress' => [
                'street1' => $customer->current_house_no ?? '',
                'street2' => $customer->current_street ?? '',
                'postOffice' => $customer->current_pincode ?? '',
                'pinCode' => $customer->current_pincode ?? '',
                'city' => $customer->current_city,
                'state' => $customer->current_state ?? '',
                'permanentAddress' => [
                    'street1' => $customer->permanent_house_no ?? '',
                    'street2' => $customer->permanent_street ?? '',
                    'postOffice' => $customer->permanent_pincode ?? '',
                    'pinCode' => $customer->permanent_pincode ?? '',
                    'city' => $customer->permanent_city ?? '',
                    'state' => $customer->permanent_state ?? '',
                ],
            ],
        ];

        $responseData = [
            'customerId' => $customer->customerId,
            'profile' => $profile,
            'enrollmentList' => $enrollmentList,
            'IDProofStatus' => $customer->id_proof_status ?? 'Not Verified',
            'IDProofType' => $this->idProofTypeLabel($customer->id_proof_type),
            'IDProofURL' => $customer->id_proof_front_side_url ?? '',
            'IDProofNumber' => $customer->id_proof_number ?? '',
        ];

        return [
            'data' => [
                'Response' => [
                    'data' => $responseData,
                ],
            ],
            'error' => [
                'status' => 200,
                'message' => 'success',
                'description' => '',
            ],
        ];
    }

    /** Map id_proof_type (int) to label string per KYC doc (e.g. PAN CARD, Aadhar). */
    private function idProofTypeLabel(?int $type): string
    {
        return match ($type) {
            1 => 'PAN CARD',
            2 => 'Aadhar',
            3 => 'Voter',
            7 => 'Driving Licence',
            default => $type !== null ? (string) $type : '',
        };
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
     * Proxies to third-party with Bearer token in header; syncs response into schemes table;
     * returns scheme list from the schemes table (only unique schemes stored).
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

        $schemes = Scheme::where('store_id', $storeId)
            ->orderBy('scheme_name')
            ->orderBy('no_of_installment')
            ->get()
            ->map(fn (Scheme $s) => [
                'id' => $s->id,
                'scheme_name' => $s->scheme_name,
                'no_of_installment' => $s->no_of_installment,
                'monthly_emi_per_month' => $s->min_installment_amount,
                'min_installment_amount' => $s->min_installment_amount,
                'max_installment_amount' => $s->max_installment_amount,
                'weight_allocation' => $s->weight_allocation,
            ])
            ->values()
            ->all();

        return response()->json(['data' => $schemes]);
    }

    /**
     * Get Customer Ledger – transaction history and financial info by enrollment number.
     * GET /api/externals/getCustomerLedgerReport?EnrollmentNo=...
     * Proxies to third-party: .../thirdparty/api/externals/getCustomerLedgerReport?access_token=...&EnrollmentNo=...
     */
    public function getCustomerLedgerReport(Request $request): JsonResponse
    {
        $request->validate([
            'EnrollmentNo' => 'required|string|max:50',
        ], [
            'EnrollmentNo.required' => 'EnrollmentNo is required',
        ]);

        $enrollmentNo = $request->query('EnrollmentNo');

        try {
            $response = $this->thirdPartyApi->getWithAccessTokenInQuery(self::THIRDPARTY_GET_CUSTOMER_LEDGER_PATH, [
                'EnrollmentNo' => $enrollmentNo,
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
                'data' => $body['data'] ?? (object) [],
                'error' => $error,
            ], $status >= 400 ? $status : 200);
        }

        return response()->json($response);
    }
}
