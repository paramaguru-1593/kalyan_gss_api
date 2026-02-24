<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
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

        $customer = Customer::where('mobile_no', $validated['mobile_no'])->first();

        if (! $customer) {
            return response()->json([
                'message' => 'Customer not found for the given mobile number.',
                'status' => 404,
            ], 404);
        }

        $customer->id_proof_type = (int) $validated['id_proof_type'];
        $customer->id_proof_number = $validated['id_proof_number'];
        $customer->id_proof_front_side_url = $validated['id_proof_front_side'];
        $customer->id_proof_back_side_url = $validated['id_proof_back_side'] ?? null;
        $customer->id_proof_status = 'Verified';
        $customer->save();

        return response()->json([
            'message' => 'KYC Updated Successfull !!',
            'status' => 200,
        ]);
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

        $customer = Customer::where('mobile_no', $validated['mobile_no'])->first();

        if (! $customer) {
            return response()->json([
                'message' => 'Customer not found for the given mobile number.',
                'status' => 404,
            ], 404);
        }

        $customer->bank_account_no = $validated['bank_account_no'];
        $customer->account_holder_name = $validated['account_holder_name'];
        $customer->account_holder_name_bank = $validated['account_holder_name_bank'];
        $customer->ifsc_code = $validated['ifsc_code'];
        $customer->bank_book_url = $validated['file'];
        $customer->name_match_percentage = $validated['name_match_percentage'];
        $customer->save();

        return response()->json([
            'message' => 'Bank Details Updated Successfull !!',
            'status' => 200,
        ]);
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
     * Customer KYC info – get customer details, address, KYC and bank info by mobile_no.
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

        $customer = Customer::where('mobile_no', $request->input('mobile_no'))->first();

        if (! $customer) {
            return response()->json([
                'message' => 'Customer not found',
                'status' => 400,
            ], 400);
        }

        return response()->json([
            'customer_details' => [
                'customerId' => $customer->customerId ?? $customer->id,
                'first_name' => $customer->first_name ?? '',
                'last_name' => $customer->last_name ?? '',
                'mobile_no' => $customer->mobile_no ?? '',
                'emailId' => $customer->email ?? '',
                'gender' => $customer->gender ? strtolower($customer->gender) : '',
                'date_of_birth' => $customer->date_of_birth?->format('Y-m-d') ?? '',
                'customer_code' => $customer->customer_code ?? '',
                'nominee_details' => [
                    'nominee_name' => $customer->nominee_name ?? '',
                    'relation_of_nominee' => $customer->relation_of_nominee ?? '',
                    'nominee_dob' => $customer->nominee_dob?->format('Y-m-d') ?? '',
                    'nominee_mobile_number' => $customer->nominee_mobile_number ?? '',
                    'nominee_address' => $customer->nominee_address ?? '',
                ],
                'address' => [
                    'current_address' => [
                        'current_house_no' => $customer->current_house_no ?? '',
                        'current_street' => $customer->current_street ?? '',
                        'current_city' => $customer->current_city ?? '',
                        'current_state' => $customer->current_state ?? '',
                        'current_pincode' => $this->pincodeToInt($customer->current_pincode),
                    ],
                    'permanent_address' => [
                        'permanent_house_no' => $customer->permanent_house_no ?? '',
                        'permanent_street' => $customer->permanent_street ?? '',
                        'permanent_city' => $customer->permanent_city ?? '',
                        'permanent_state' => $customer->permanent_state ?? '',
                        'permanent_pincode' => $this->pincodeToInt($customer->permanent_pincode),
                    ],
                ],
                'kyc_details' => [
                    'mobile_no' => $customer->mobile_no ?? '',
                    'id_proof_type' => $customer->id_proof_type,
                    'id_proof_front_side' => $customer->id_proof_front_side_url,
                    'id_proof_back_side' => $customer->id_proof_back_side_url,
                    'id_proof_number' => $this->maskString($customer->id_proof_number, 5),
                ],
                'bank_details' => [
                    'bank_account_no' => $this->maskString($customer->bank_account_no, 3),
                    'account_holder_name' => $customer->account_holder_name ?? '',
                    'account_holder_name_bank' => $customer->account_holder_name_bank ?? '',
                    'ifsc_code' => $customer->ifsc_code ?? '',
                    'name_match_percentage' => $customer->name_match_percentage !== null
                        ? number_format((float) $customer->name_match_percentage, 2, '.', '')
                        : '',
                ],
            ],
            'profile_completeness' => $this->getProfileCompleteness($customer),
        ]);
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
            'id_proof_back_side_url' => $customer->id_proof_back_side_url,
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
