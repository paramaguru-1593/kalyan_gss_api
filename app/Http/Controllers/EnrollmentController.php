<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class EnrollmentController extends Controller
{
    /**
     * Create and persist enrollment (scheme, EMI, tenure, nominee, payment).
     * POST /thirdparty/api/enroll_new
     * Header: content-type: application/json. access_token optional (not validated).
     */
    public function enrollNew(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
            'scheme_id' => 'required|integer',
            'customer_id' => 'required|integer',
            'mobile_no' => 'required|string|max:50',
            'tenure' => 'required|integer',
            'emi_amount' => 'required|numeric',
            'mode_of_pay' => 'required|string|max:50',
            'nominee_first_name' => 'required|string|max:255',
            'nominee_last_name' => 'required|string|max:255',
            'nominee_mobile_no' => 'required|string|max:50',
            'nominee_relation' => 'required|string|max:50',
            'nominee_pincode_id' => 'required|integer',
            'nominee_state' => 'required|string|max:255',
            'nominee_district' => 'required|string|max:255',
            'nominee_city' => 'required|string|max:255',
            'nominee_street' => 'required|string|max:255',
            'nominee_house_no' => 'required|max:255', // Char in doc; sample uses number 10
        ], [], [
            'scheme_id' => 'scheme_id',
            'customer_id' => 'customer_id',
            'mobile_no' => 'mobile_no',
            'tenure' => 'tenure',
            'emi_amount' => 'emi_amount',
            'mode_of_pay' => 'mode_of_pay',
            'nominee_first_name' => 'nominee_first_name',
            'nominee_last_name' => 'nominee_last_name',
            'nominee_mobile_no' => 'nominee_mobile_no',
            'nominee_relation' => 'nominee_relation',
            'nominee_pincode_id' => 'nominee_pincode_id',
            'nominee_state' => 'nominee_state',
            'nominee_district' => 'nominee_district',
            'nominee_city' => 'nominee_city',
            'nominee_street' => 'nominee_street',
            'nominee_house_no' => 'nominee_house_no',
        ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Invalid Details',
                'status' => 400,
            ], 400);
        }

        $result = $this->persistEnrollment($validated);

        if ($result === null) {
            return response()->json([
                'message' => 'Invalid Details',
                'status' => 400,
            ], 400);
        }

        return response()->json([
            'message' => 'Success',
            'account_no' => $result['account_no'],
            'receipt_no' => $result['receipt_no'],
            'status' => 200,
        ]);
    }

    /**
     * Persist enrollment and return account_no & receipt_no, or null on failure.
     */
    private function persistEnrollment(array $data): ?array
    {
        // TODO: Replace with DB insert and real account_no/receipt_no generation.
        // Stub: accept valid data and return sample IDs.
        return [
            'account_no' => 2008754210,
            'receipt_no' => 20005,
        ];
    }
}
