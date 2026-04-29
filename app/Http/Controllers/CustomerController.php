<?php

namespace App\Http\Controllers;

use App\Exceptions\ThirdPartyApiException;
use App\Models\Customer;
use App\Services\ThirdPartyApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CustomerController extends Controller
{
    private const THIRDPARTY_CUSTOMER_KYC_INFO_PATH = 'thirdparty/api/customerkycinfo';
    private const THIRDPARTY_CUSTOMER_KYC_UPDATION_PATH = 'thirdparty/api/customerkycupdation';
    private const THIRDPARTY_CUSTOMER_BANK_UPDATION_PATH = 'thirdparty/api/customerbankdetail_updation';

    public function __construct(
        private readonly ThirdPartyApiService $thirdPartyApi,
    ) {
    }

    /**
     * Update personal details by mobile number.
     * Single API: validate → apply → response (3 steps in one endpoint).
     */
    public function updatePersonalDetails(Request $request): JsonResponse
    {
        // ✅ Validate request
        $validated = $request->validate([
            'mobileNumber' => 'required|string|max:50',
            'first_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'emailAddress' => 'nullable|email|max:100',
            'dateOfBirth' => 'nullable|date',
            'gender' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'stateName' => 'nullable|string|max:100',
            'pincode' => 'nullable|string|max:20',
            'nomineeName' => 'nullable|string|max:255',
            'nomineeRelationship' => 'nullable|string|max:100',
            'nomineeDob' => 'nullable|date',
            'nomineeAddress' => 'nullable|string|max:500',
            'nomineeContact' => 'nullable|string|max:50',
        ]);

        // ✅ Find customer
        $customer = Customer::where('mobile_no', $validated['mobileNumber'])->first();

        if (!$customer) {
            return response()->json([
                'status' => 'error',
                'message' => 'Customer not found for the given mobile number.',
            ], 404);
        }

        // ✅ Map request fields to database columns
        $mappedData = [
            'first_name' => $validated['first_name'] ?? null,
            'last_name' => $validated['last_name'] ?? null,
            'email' => $validated['emailAddress'] ?? null,
            'date_of_birth' => $validated['dateOfBirth'] ?? null,
            'gender' => $validated['gender'] ?? null,
            'current_street' => $validated['address'] ?? null,
            'current_city' => $validated['city'] ?? null,
            'current_state' => $validated['stateName'] ?? null,
            'current_pincode' => $validated['pincode'] ?? null,
            'nominee_name' => $validated['nomineeName'] ?? null,
            'relation_of_nominee' => $validated['nomineeRelationship'] ?? null,
            'nominee_dob' => $validated['nomineeDob'] ?? null,
            'nominee_address' => $validated['nomineeAddress'] ?? null,
            'nominee_mobile_number' => $validated['nomineeContact'] ?? null,
        ];

        // Remove null values (so only sent fields are updated)
        $filteredData = array_filter($mappedData, fn($value) => !is_null($value));

        // ✅ Update customer
        $customer->fill($filteredData);
        $customer->save();

        // ✅ Response
        return response()->json([
            'status' => 'success',
            'message' => 'Customer details updated successfully.',
            'customer' => [
                'id' => $customer->id,
                'first_name' => $customer->first_name,
                'last_name' => $customer->last_name,
                'fullName' => $customer->full_name,
                'mobile_no' => $customer->mobile_no,
                'email' => $customer->email,
                'date_of_birth' => $customer->date_of_birth?->format('Y-m-d'),
                'gender' => $customer->gender,
                'address' => $customer->current_street,
                'city' => $customer->current_city,
                'state' => $customer->current_state,
                'pincode' => $customer->current_pincode,
                'nominee_name' => $customer->nominee_name,
                'nominee_relationship' => $customer->relation_of_nominee,
                'nominee_dob' => $customer->nominee_dob?->format('Y-m-d'),
                'nominee_address' => $customer->nominee_address,
                'nominee_contact' => $customer->nominee_mobile_number,
            ]
        ]);
    }

    /**
     * Customer KYC updation – same input as KycController; updates customers table.
     * POST body: mobile_no, id_proof_type, id_proof_front_side, id_proof_back_side (optional), id_proof_number.
     */
    public function customerKycUpdation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mobile_no' => 'required|string|max:50',
            'id_proof_type' => 'required|integer|in:1,2,3,7', // 1=Pan, 2=Aadhar, 3=Voter, 7=Driving Licence
            'id_proof_front_side' => 'required|string|max:500',
            'id_proof_back_side' => 'nullable',
            'id_proof_number' => 'required|string|max:50',
        ]);

        $payload = [
            'mobile_no' => $validated['mobile_no'],
            'id_proof_type' => (int) $validated['id_proof_type'],
            'id_proof_front_side' => $validated['id_proof_front_side'],
            'id_proof_back_side' => $validated['id_proof_back_side'] ?? null,
            'id_proof_number' => $validated['id_proof_number'],
        ];

        try {
            $response = $this->thirdPartyApi->postWithAccessTokenInQuery(
                self::THIRDPARTY_CUSTOMER_KYC_UPDATION_PATH,
                [],
                $payload
            );
        } catch (ThirdPartyApiException $e) {
            $status = $e->getHttpStatus() ?: 502;
            $body = $e->getResponseBody() ?? [];

            if (isset($body['message'], $body['status'])) {
                $errorStatus = (int) $body['status'];
                return response()->json($body, $errorStatus > 0 ? $errorStatus : $status);
            }

            return response()->json([
                'message' => $e->getMessage(),
                'status' => $status,
            ], $status >= 400 ? $status : 502);
        }

        if (! $this->isThirdPartySuccessResponse($response)) {
            return response()->json([
                'message' => (string) ($response['message'] ?? 'KYC Details Not Updated!!'),
                'status' => (int) ($response['status'] ?? 400),
            ], (int) ($response['status'] ?? 400));
        }

        Customer::updateOrCreate(
            ['mobile_no' => $validated['mobile_no']],
            [
                'id_proof_type' => (int) $validated['id_proof_type'],
                'id_proof_number' => $validated['id_proof_number'],
                'id_proof_front_side_url' => $validated['id_proof_front_side'],
                'id_proof_back_side_url' => $validated['id_proof_back_side'] ?? null,
                'id_proof_status' => 'Verified',
            ]
        );

        return response()->json($response);
    }

    /**
     * Customer bank detail updation – same input as KycController; updates customers table.
     * POST body: mobile_no, bank_account_no, account_holder_name, account_holder_name_bank, ifsc_code, file, name_match_percentage.
     */
    public function customerBankDetailUpdation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mobile_no' => 'required|string|max:50',
            'bank_account_no' => 'required|string|max:50',
            'account_holder_name' => 'required|string|max:255',
            'account_holder_name_bank' => 'required|string|max:255',
            'ifsc_code' => 'required|string|max:50',
            'file' => 'required|string',
            'name_match_percentage' => 'required|max:100',
        ]);

        $payload = [
            'mobile_no' => $validated['mobile_no'],
            'bank_account_no' => $validated['bank_account_no'],
            'account_holder_name' => $validated['account_holder_name'],
            'ifsc_code' => $validated['ifsc_code'],
            'file' => $validated['file'],
            'account_holder_name_bank' => $validated['account_holder_name_bank'],
            'name_match_percentage' => $validated['name_match_percentage'],
        ];

        try {
            $response = $this->thirdPartyApi->postWithAccessTokenInQuery(
                self::THIRDPARTY_CUSTOMER_BANK_UPDATION_PATH,
                [],
                $payload
            );
        } catch (ThirdPartyApiException $e) {
            $status = $e->getHttpStatus() ?: 502;
            $body = $e->getResponseBody() ?? [];

            if (isset($body['message'], $body['status'])) {
                $errorStatus = (int) $body['status'];
                return response()->json($body, $errorStatus > 0 ? $errorStatus : $status);
            }

            return response()->json([
                'message' => $e->getMessage(),
                'status' => $status,
            ], $status >= 400 ? $status : 502);
        }

        if (! $this->isThirdPartySuccessResponse($response)) {
            return response()->json([
                'message' => (string) ($response['message'] ?? 'Invalid Bank Details'),
                'status' => (int) ($response['status'] ?? 400),
            ], (int) ($response['status'] ?? 400));
        }

        $nameMatchPercentage = null;
        if (is_numeric($validated['name_match_percentage'])) {
            $nameMatchPercentage = (float) $validated['name_match_percentage'];
        }

        Customer::updateOrCreate(
            ['mobile_no' => $validated['mobile_no']],
            [
                'bank_account_no' => $validated['bank_account_no'],
                'account_holder_name' => $validated['account_holder_name'],
                'account_holder_name_bank' => $validated['account_holder_name_bank'],
                'ifsc_code' => $validated['ifsc_code'],
                'bank_book_url' => $validated['file'],
                'name_match_percentage' => $nameMatchPercentage,
            ]
        );

        return response()->json($response);
    }

    /**
     * Customer profile completeness score.
     * POST body: { "mobile_number": "9361901823" }
     * Response: score out of 100, filled/total, missing_fields.
     */
    public function profileCompleteness(Request $request): JsonResponse
    {
        $request->validate([
            'mobile_number' => 'required|string|max:50',
        ], [
            'mobile_number.required' => 'mobile_number is required',
        ]);

        $customer = Customer::where('mobile_no', $request->input('mobile_number'))->first();

        if (! $customer) {
            return response()->json([
                'message' => 'Customer not found',
                'status' => 400,
            ], 400);
        }

        return response()->json([
            'profile_completeness' => $this->getProfileCompleteness($customer),
        ]);
    }

    /**
     * Customer KYC info – get customer details, address, KYC and bank info by mobile_no (from local DB).
     * Request: { "mobile_no": "9361901823" }
     * Response: customer_details with address, kyc_details, bank_details (masked where needed).
     */
    public function customerKycInfo(Request $request): JsonResponse
    {
        $request->validate([
            'mobile_no' => 'required|string|max:50',
        ], [
            'mobile_no.required' => 'mobile_no is required',
        ]);

        $mobileNo = trim((string) $request->input('mobile_no'));

        try {
            $response = $this->thirdPartyApi->postWithAccessTokenInQuery(
                self::THIRDPARTY_CUSTOMER_KYC_INFO_PATH,
                [],
                ['mobile_no' => $mobileNo]
            );

            try {
                $this->syncCustomerKycFromThirdPartyResponse($response, $mobileNo);
            } catch (\Throwable $e) {
                // Do not fail the API response if third-party call succeeded but local sync failed.
                Log::warning('Customer KYC sync failed after third-party success', [
                    'mobile_no' => $mobileNo,
                    'message' => $e->getMessage(),
                ]);
            }

            return response()->json($response);
        } catch (ThirdPartyApiException $e) {
            $status = $e->getHttpStatus() ?: 502;
            $body = $e->getResponseBody() ?? [];

            // Forward common third-party business errors as-is (e.g., Customer not found).
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
                'data' => (object) [],
                'error' => $error,
            ], $status >= 400 ? $status : 200);
        }
    }

    /**
     * Sync third-party customer KYC response into local customers table.
     *
     * Accepts both response shapes:
     * - customer_details contains kyc_details/bank_details
     * - customer_details + top-level kyc_details/bank_details
     */
    private function syncCustomerKycFromThirdPartyResponse(array $response, string $requestedMobile): void
    {
        $customerDetails = $response['customer_details'] ?? null;
        if (! is_array($customerDetails)) {
            return;
        }

        $kycDetails = $customerDetails['kyc_details'] ?? $response['kyc_details'] ?? [];
        if (! is_array($kycDetails)) {
            $kycDetails = [];
        }

        $bankDetails = $customerDetails['bank_details'] ?? $response['bank_details'] ?? [];
        if (! is_array($bankDetails)) {
            $bankDetails = [];
        }

        $address = $customerDetails['address'] ?? [];
        if (! is_array($address)) {
            $address = [];
        }
        $currentAddress = $address['current_address'] ?? [];
        if (! is_array($currentAddress)) {
            $currentAddress = [];
        }
        $permanentAddress = $address['permanent_address'] ?? [];
        if (! is_array($permanentAddress)) {
            $permanentAddress = [];
        }

        $mobileNo = trim((string) ($customerDetails['mobile_no'] ?? $kycDetails['mobile_no'] ?? $requestedMobile));
        if ($mobileNo === '') {
            return;
        }

        $nameMatchPercentage = null;
        if (isset($bankDetails['name_match_percentage']) && is_numeric($bankDetails['name_match_percentage'])) {
            $nameMatchPercentage = (float) $bankDetails['name_match_percentage'];
        }

        Customer::updateOrCreate(
            ['mobile_no' => $mobileNo],
            [
                'customerId' => $this->normalizeNullableInt($customerDetails['customerId'] ?? null),
                'customer_code' => $this->normalizeNullableString($customerDetails['customer_code'] ?? null),
                'first_name' => $this->normalizeNullableString($customerDetails['first_name'] ?? null),
                'last_name' => $this->normalizeNullableString($customerDetails['last_name'] ?? null),
                'email' => $this->normalizeNullableString($customerDetails['emailId'] ?? null),
                'gender' => $this->normalizeNullableString($customerDetails['gender'] ?? null),
                'date_of_birth' => $this->normalizeNullableString($customerDetails['date_of_birth'] ?? null),
                'current_house_no' => $this->normalizeNullableString($currentAddress['current_house_no'] ?? null),
                'current_street' => $this->normalizeNullableString($currentAddress['current_street'] ?? null),
                'current_city' => $this->normalizeNullableString($currentAddress['current_city'] ?? null),
                'current_state' => $this->normalizeNullableString($currentAddress['current_state'] ?? null),
                'current_pincode' => $this->normalizeNullableString($currentAddress['current_pincode'] ?? null),
                'permanent_house_no' => $this->normalizeNullableString($permanentAddress['permanent_house_no'] ?? null),
                'permanent_street' => $this->normalizeNullableString($permanentAddress['permanent_street'] ?? null),
                'permanent_city' => $this->normalizeNullableString($permanentAddress['permanent_city'] ?? null),
                'permanent_state' => $this->normalizeNullableString($permanentAddress['permanent_state'] ?? null),
                'permanent_pincode' => $this->normalizeNullableString($permanentAddress['permanent_pincode'] ?? null),
                'id_proof_type' => $this->normalizeNullableInt($kycDetails['id_proof_type'] ?? null),
                'id_proof_number' => $this->normalizeNullableString($kycDetails['id_proof_number'] ?? null),
                'id_proof_front_side_url' => $this->normalizeNullableString($kycDetails['id_proof_front_side'] ?? null),
                'id_proof_back_side_url' => $this->normalizeNullableString($kycDetails['id_proof_back_side'] ?? null),
                'id_proof_status' => $this->normalizeNullableString($kycDetails['id_proof_number'] ?? null) ? 'Verified' : 'Not Verified',
                'bank_account_no' => $this->normalizeNullableString($bankDetails['bank_account_no'] ?? null),
                'account_holder_name' => $this->normalizeNullableString($bankDetails['account_holder_name'] ?? null),
                'account_holder_name_bank' => $this->normalizeNullableString($bankDetails['account_holder_name_bank'] ?? null),
                'ifsc_code' => $this->normalizeNullableString($bankDetails['ifsc_code'] ?? null),
                'name_match_percentage' => $nameMatchPercentage,
            ]
        );
    }

    private function maskString(?string $value, int $visibleLastChars): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        $len = strlen($value);
        if ($len <= $visibleLastChars) {
            return str_repeat('*', $len);
        }
        return str_repeat('*', $len - $visibleLastChars) . substr($value, -$visibleLastChars);
    }

    private function pincodeToInt(?string $value)
    {
        if ($value === null || $value === '') {
            return 0;
        }
        return (int) preg_replace('/\D/', '', $value) ?: 0;
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function normalizeNullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    private function isThirdPartySuccessResponse(array $response): bool
    {
        if (isset($response['status']) && (int) $response['status'] !== 200) {
            return false;
        }

        return true;
    }

    /**
     * Profile completeness: fields that users can fill (from personal, KYC, bank flows).
     * Returns: filled count, total count, score out of 100, and list of missing field keys.
     */
    public function getProfileCompleteness(Customer $customer): array
    {
        $fields = [
            // Personal (updatePersonalDetails)
            'first_name' => $customer->first_name,
            'last_name' => $customer->last_name,
            'email' => $customer->email,
            'date_of_birth' => $customer->date_of_birth,
            'gender' => $customer->gender,
            'current_street' => $customer->current_street,
            'current_city' => $customer->current_city,
            'current_state' => $customer->current_state,
            'current_pincode' => $customer->current_pincode,
            'nominee_name' => $customer->nominee_name,
            'relation_of_nominee' => $customer->relation_of_nominee,
            'nominee_dob' => $customer->nominee_dob,
            'nominee_address' => $customer->nominee_address,
            'nominee_mobile_number' => $customer->nominee_mobile_number,
            // KYC (customerKycUpdation)
            'id_proof_type' => $customer->id_proof_type !== null ? (string) $customer->id_proof_type : null,
            'id_proof_number' => $customer->id_proof_number,
            'id_proof_front_side_url' => $customer->id_proof_front_side_url,
            // 'id_proof_back_side_url' => $customer->id_proof_back_side_url,
            // Bank (customerBankDetailUpdation)
            'bank_account_no' => $customer->bank_account_no,
            'account_holder_name' => $customer->account_holder_name,
            'account_holder_name_bank' => $customer->account_holder_name_bank,
            'ifsc_code' => $customer->ifsc_code,
            'bank_book_url' => $customer->bank_book_url,
            'name_match_percentage' => $customer->name_match_percentage !== null ? (string) $customer->name_match_percentage : null,
        ];

        $filled = 0;
        $missing = [];
        foreach ($fields as $key => $value) {
            $isEmpty = $value === null || $value === '';
            if (! $isEmpty) {
                $filled++;
            } else {
                $missing[] = $key;
            }
        }

        $total = count($fields);
        $scoreOutOf100 = $total > 0 ? (int) round(($filled / $total) * 100) : 0;

        return [
            'score' => $scoreOutOf100,
            'out_of' => 100,
            'filled' => $filled,
            'total' => $total,
            'missing_fields' => $missing,
        ];
    }
}
