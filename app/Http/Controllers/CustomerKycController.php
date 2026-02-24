<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class CustomerKycController extends Controller
{
    /**
     * Get customer KYC details (profile, addresses, ID proof, bank details).
     * POST /thirdparty/api/customerkycinfo
     * Header: content-type: application/json. access_token optional (not validated).
     */
    public function customerKycInfo(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'mobile_no' => 'required|string|max:50',
            ], [
                'mobile_no.required' => 'mobile_no is required',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'mobile_no is required',
                'status' => 400,
            ], 400);
        }

        $mobileNo = $request->input('mobile_no');

        $customerDetails = $this->findCustomerKycByMobile($mobileNo);

        if ($customerDetails === null) {
            return response()->json([
                'message' => 'Customer not found',
                'status' => 400,
            ], 400);
        }

        return response()->json([
            'customer_details' => $customerDetails,
        ]);
    }

    /**
     * Find customer KYC by mobile number. Return null when not found.
     */
    private function findCustomerKycByMobile(string $mobileNo): ?array
    {
        $mobileNo = trim($mobileNo);
        if ($mobileNo === '') {
            return null;
        }

        // TODO: Replace with DB or external service lookup.
        // Stub: return sample structure for any non-empty mobile.
        return [
            'customerId' => 58451694517000,
            'first_name' => 'TEST',
            'last_name' => 'S',
            'mobile_no' => $mobileNo,
            'emailId' => '',
            'gender' => 'male',
            'date_of_birth' => '1987-12-09',
            'customer_code' => 'EQL67334000',
            'address' => [
                'current_address' => [
                    'current_house_no' => '12',
                    'current_street' => 'KL',
                    'current_city' => 'CHENNAI CITY CORPORATION',
                    'current_state' => 'TAMIL NADU',
                    'current_pincode' => 600078,
                ],
                'permanent_address' => [
                    'permanent_house_no' => '12',
                    'permanent_street' => 'KL',
                    'permanent_city' => 'CHENNAI CITY CORPORATION',
                    'permanent_state' => 'TAMIL NADU',
                    'permanent_pincode' => 600078,
                ],
            ],
            'kyc_details' => [
                'mobile_no' => $mobileNo,
                'id_proof_type' => null,
                'id_proof_front_side' => 'https://docmanuat-server.kalyanjewellers.company/uploads/de83f23a-eba7-4cee-9aa9-ec82fdfgh.jpg',
                'id_proof_back_side' => null,
                'id_proof_number' => '*********00125',
            ],
            'bank_details' => [
                'bank_account_no' => '********001',
                'account_holder_name' => 'TEST S ',
                'account_holder_name_bank' => 'SBI',
                'ifsc_code' => 'ICB0025',
                'name_match_percentage' => '95.00',
            ],
        ];
    }
}
