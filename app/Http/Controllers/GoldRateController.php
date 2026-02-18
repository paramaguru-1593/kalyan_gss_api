<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class GoldRateController extends Controller
{
    /**
     * Get Gold Rate Details – latest gold rate by date, region, location.
     * POST /thirdparty/api/getstoregoldrate
     */
    public function getStoreGoldRate(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'Date' => 'required|string|max:50',
                'Region' => 'required|string|max:100',
                'Location' => 'required|string|max:255',
                'Transaction_ID' => 'required|string|size:8',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Transaction id is not unique!!',
                'status' => 400,
            ], 400);
        }

        $result = $this->fetchGoldRate($validated);

        if ($result === null) {
            return response()->json([
                'message' => 'Transaction id is not unique!!',
                'status' => 400,
            ], 400);
        }

        return response()->json($result);
    }

    /**
     * Scheme Benefits – summarized benefits and short terms for a scheme.
     * POST /thirdparty/api/externals/schemebenifits
     */
    public function schemeBenefits(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'scheme_id' => 'required|integer',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Invalid Scheme ID',
                'status' => 400,
            ], 400);
        }

        $schemeId = $request->input('scheme_id');
        $content = $this->findSchemeBenefits($schemeId);

        if ($content === null) {
            return response()->json([
                'message' => 'Invalid Scheme ID',
                'status' => 400,
            ], 400);
        }

        return response()->json([
            'data' => ['content' => $content],
            'status' => 200,
        ]);
    }

    /**
     * Nominee Details – nominee address and related details by customer_id.
     * POST /thirdparty/api/externals/nomineedetails
     */
    public function nomineeDetails(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'customer_id' => 'required|integer',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Customer ID',
                'status' => 400,
            ], 400);
        }

        $customerId = $request->input('customer_id');
        $data = $this->findNomineeDetails($customerId);

        if ($data === null) {
            return response()->json([
                'message' => 'Customer ID',
                'status' => 400,
            ], 400);
        }

        return response()->json([
            'data' => $data,
            'status' => 200,
        ]);
    }

    /**
     * Get Pincode Details – area, city, district, state by pincode.
     * POST /thirdparty/api/externals/get-pincode-details
     */
    public function getPincodeDetails(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'pincode' => 'required|max:50',
            ], [
                'pincode.required' => 'Pinocde is required',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Pinocde is required',
                'status' => 400,
            ], 400);
        }

        $pincode = $request->input('pincode');
        $data = $this->findPincodeDetails($pincode);

        if ($data === null) {
            return response()->json([
                'message' => 'Pincode not found',
                'status' => 400,
            ], 400);
        }

        return response()->json([
            'data' => $data,
        ]);
    }

    /**
     * Fetch gold rate for given date/region/location. Return null if Transaction_ID not unique or invalid.
     */
    private function fetchGoldRate(array $input): ?array
    {
        // TODO: Check Transaction_ID uniqueness (e.g. DB/cache) and fetch real rate.
        return [
            'MetalType' => 'Gold',
            'Purity' => '995',
            'NetRate' => 15690.000,
        ];
    }

    /**
     * Get scheme benefits content by scheme_id. Return null for invalid scheme.
     */
    private function findSchemeBenefits(int $schemeId): ?array
    {
        // TODO: Replace with DB or config lookup.
        return [
            '1.You can pay the monthly installments in any of the Kalyan Jewellers showrooms in India, My Kalyan Mini Stores and Online at payments.kalyanjewellers.net',
            '2.You can pay the monthly installments in any of the Kalyan Jewellers showrooms in India, My Kalyan Mini Stores and Online at payments.kalyanjewellers.net ',
        ];
    }

    /**
     * Get nominee details by customer_id. Return null when not found.
     */
    private function findNomineeDetails(int $customerId): ?array
    {
        // TODO: Replace with DB lookup.
        return [
            'nominee_first_name' => 'Rajan',
            'nominee_last_name' => 'R',
            'nominee_mobile_no' => '9994795321',
            'nominee_house_no' => '99',
            'nominee_street' => '1st street',
            'nominee_pincode' => '600106',
            'nominee_state_id' => '11',
            'nominee_city_id' => '11',
            'nominee_post_office_id' => '11',
            'nominee_state' => [
                'id' => 31,
                'name' => 'TAMIL NADU',
            ],
            'nominee_district' => [
                'id' => 110,
                'district_name' => 'CHENNAI',
            ],
            'nominee_postoffice' => [
                [
                    'id' => 113923,
                    'postoffice_name' => 'ARUMBAKKAM S.O',
                ],
            ],
            'nominee_city' => [
                [
                    'id' => 18832,
                    'city_name' => 'EGMORE NUNGAMBAKKAM',
                ],
            ],
        ];
    }

    /**
     * Get location details by pincode. Return null when not found.
     */
    private function findPincodeDetails($pincode): ?array
    {
        $pincode = trim((string) $pincode);
        if ($pincode === '') {
            return null;
        }

        // TODO: Replace with DB or external lookup (e.g. India pincode API).
        $pincodeInt = (int) preg_replace('/\D/', '', $pincode);
        if ($pincodeInt <= 0) {
            return null;
        }

        return [
            'pincode' => $pincodeInt,
            'state' => [
                'id' => 31,
                'name' => 'TAMIL NADU',
            ],
            'district' => [
                'id' => 110,
                'district_name' => 'CHENNAI',
            ],
            'postoffice' => [
                ['id' => 113923, 'postoffice_name' => 'ARUMBAKKAM S.O'],
                ['id' => 113926, 'postoffice_name' => 'D G VAISHNAV COLLEGE S.O'],
            ],
            'city' => [
                ['id' => 18832, 'city_name' => 'EGMORE NUNGAMBAKKAM'],
                ['id' => 18833, 'city_name' => 'MADURAVOYAL'],
            ],
        ];
    }
}
