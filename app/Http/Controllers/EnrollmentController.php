<?php

namespace App\Http\Controllers;

use App\Exceptions\ThirdPartyApiException;
use App\Services\ThirdPartyApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EnrollmentController extends Controller
{
    public function __construct(
        private readonly ThirdPartyApiService $thirdPartyApi,
    ) {
    }

    /**
     * Create enrollment via MyKalyan (scheme, EMI, tenure, nominee, payment).
     * POST /api/v2/enroll_new — proxies to POST {base}/thirdparty/api/enroll_new?access_token=...
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
                'externalId' => 'nullable|string|max:100',
                'nominee_first_name' => 'required|string|max:255',
                'nominee_last_name' => 'required|string|max:255',
                'nominee_mobile_no' => 'required|string|max:50',
                'nominee_relation' => 'required|string|max:50',
                'nominee_pincode_id' => 'required|integer',
                'nominee_state' => 'required|string|max:255',
                'nominee_district' => 'required|string|max:255',
                'nominee_city' => 'required|string|max:255',
                'nominee_street' => 'required|string|max:255',
                'nominee_house_no' => 'required',
            ], [], [
                'scheme_id' => 'scheme_id',
                'customer_id' => 'customer_id',
                'mobile_no' => 'mobile_no',
                'tenure' => 'tenure',
                'emi_amount' => 'emi_amount',
                'mode_of_pay' => 'mode_of_pay',
                'externalId' => 'externalId',
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

        $path = (string) config(
            'thirdparty.mykalyan.enroll_new_path',
            'thirdparty/api/enroll_new'
        );
        $path = ltrim($path, '/');

        $payload = [
            'RequestData' => [
                'personalDetails' => [
                    'mobileNumber' => $validated['mobile_no'],
                ],
                'schemeInfo' => [
                    'scheme_id' => (int) $validated['scheme_id'],
                    'customer_id' => (int) $validated['customer_id'],
                    'tenure' => (int) $validated['tenure'],
                    'emi_amount' => (string) $validated['emi_amount'],
                    'mode_of_pay' => $validated['mode_of_pay'],
                    'externalId' => $validated['externalId'] ?? null,
                ],
                'nomineeInfo' => [
                    'firstName' => $validated['nominee_first_name'],
                    'lastName' => $validated['nominee_last_name'],
                    'mobileNumber' => $validated['nominee_mobile_no'],
                    'relation' => $validated['nominee_relation'],
                    'pincode_id' => (int) $validated['nominee_pincode_id'],
                    'state' => $validated['nominee_state'],
                    'district' => $validated['nominee_district'],
                    'city' => $validated['nominee_city'],
                    'street' => $validated['nominee_street'],
                    'house_no' => $validated['nominee_house_no'],
                ],
            ],
        ];

        try {
            $response = $this->thirdPartyApi->postWithAccessTokenInBody($path, $payload);
            return response()->json($response);
        } catch (ThirdPartyApiException $e) {
            $status = $e->getHttpStatus() ?: 502;
            $body = $e->getResponseBody() ?? [];

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
}
