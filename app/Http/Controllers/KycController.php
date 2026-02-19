<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class KycController extends Controller
{
    /**
     * Customer KYC Updation – update ID proof type, number, and images.
     * POST /thirdparty/api/customerkycupdation
     * Header: content-type: application/json. access_token optional (not validated).
     */
    public function customerKycUpdation(Request $request): JsonResponse
    {
        // try {
            $validated = $request->validate([
                'mobile_no' => 'required|string|max:50',
                'id_proof_type' => 'required|integer|in:1,2,3,7', // 1=Pan, 2=Aadhar, 3=Voter, 7=Driving Licence
                'id_proof_front_side' => 'required|string|max:500',
                'id_proof_back_side' => 'nullable',
                'id_proof_number' => 'required|string|max:50',
            ]);
        // } catch (ValidationException $e) {
        //     return response()->json([
        //         'message' => 'KYC Details Not Updated!!',
        //         'status' => 400,
        //     ], 400);
        // }

        $updated = $this->updateCustomerKyc($validated);

        if (!$updated) {
            return response()->json([
                'message' => 'KYC Details Not Updated!!',
                'status' => 400,
            ], 400);
        }

        return response()->json([
            'message' => 'KYC Updated Successfull !!',
            'status' => 200,
        ]);
    }

    /**
     * Customer Bank Updation – update and validate bank details.
     * POST /thirdparty/api/customerbankdetail_updation
     * Header: content-type: application/json. access_token optional (not validated).
     */
    public function customerBankDetailUpdation(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'mobile_no' => 'required|string|max:50',
                'bank_account_no' => 'required|string|max:50',
                'account_holder_name' => 'required|string|max:255',
                'account_holder_name_bank' => 'required|string|max:255',
                'ifsc_code' => 'required|string|max:50',
                'file' => 'required|string',
                'name_match_percentage' => 'required|max:100',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Invalid Bank Details',
                'status' => 400,
            ], 400);
        }

        $updated = $this->updateCustomerBankDetails($validated);

        if (!$updated) {
            return response()->json([
                'message' => 'Invalid Bank Details',
                'status' => 400,
            ], 400);
        }

        return response()->json([
            'message' => 'Bank Details Updated Successfull !!',
            'status' => 200,
        ]);
    }

    /**
     * Persist KYC update. Return true on success, false on failure.
     */
    private function updateCustomerKyc(array $data): bool
    {
        // TODO: Replace with DB or external service update.
        return true;
    }

    /**
     * Persist bank details update and validate. Return true on success, false on failure.
     */
    private function updateCustomerBankDetails(array $data): bool
    {
        // TODO: Replace with DB or external service update and validation.
        return true;
    }
}
