<?php

namespace App\Http\Controllers;

use App\Exceptions\ThirdPartyApiException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use App\Services\ThirdPartyApiService;

class GoldRateController extends Controller
{
    private const THIRDPARTY_GET_PINCODE_DETAILS_PATH = 'thirdparty/api/externals/get-pincode-details/';

    public function __construct(
        private readonly ThirdPartyApiService $thirdPartyApi,
    ) {
    }

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

        try {
            $result = $this->fetchGoldRate($validated);
        } catch (ThirdPartyApiException $e) {
            $status = $e->getHttpStatus() ?: 502;
            $body = $e->getResponseBody() ?? [];

            // Third-party error forwarding:
            // - errorCode + errorResponse are forwarded from either body["error"] or top-level body.
            $tpError = $body['error'] ?? null;
            $tpStatus = is_array($tpError) ? ($tpError['status'] ?? null) : null;
            $tpMessage = is_array($tpError) ? ($tpError['message'] ?? null) : null;
            $tpDescription = is_array($tpError) ? ($tpError['description'] ?? null) : null;
            $mappedError = [
                'status' => (int) ($tpStatus ?? $body['status'] ?? $status),
                'message' => (string) ($tpMessage ?? $body['message'] ?? $e->getMessage()),
                'description' => (string) ($tpDescription ?? $body['description'] ?? ''),
            ];

            return response()->json([
                'data' => (object) [],
                'error' => $mappedError,
            ], $status >= 400 ? $status : 200);
        }

        if ($result === null) {
            return response()->json([
                'message' => 'Transaction id is not unique!!',
                'status' => 400,
            ], 400);
        }

        // Wrap third-party success payload into our API format.
        // Also support third-party "business errors" returned with an "error" field.
        if (isset($result['error']) && is_array($result['error'])) {
            $tpError = $result['error'];
            $errorStatus = (int) ($tpError['status'] ?? $result['status'] ?? 400);

            $mappedError = [
                'status' => $errorStatus,
                'message' => (string) ($tpError['message'] ?? $result['message'] ?? 'Request failed'),
                'description' => (string) ($tpError['description'] ?? ''),
            ];

            return response()->json([
                'data' => $result['data'] ?? (object) [],
                'error' => $mappedError,
            ], $errorStatus >= 400 ? $errorStatus : 200);
        }

        return response()->json([
            'data' => $result['data'] ?? $result,
            'error' => [
                'status' => 200,
                'message' => 'Success',
                'description' => '',
            ],
        ]);
    }

    /**
     * Scheme Benefits – summarized benefits and short terms for a scheme.
     * POST /api/v2/schemebenifits
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

        $path = (string) config(
            'thirdparty.mykalyan.scheme_benefits_path',
            'thirdparty/api/externals/schemebenifits'
        );
        $path = ltrim($path, '/');

        try {
            $response = $this->thirdPartyApi->postWithAccessTokenInQuery($path, [], [
                'scheme_id' => (int) $request->input('scheme_id'),
            ]);

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

    /**
     * Nominee Details – nominee address and related details (MyKalyan externals).
     * Proxies to: POST {base}/thirdparty/api/externals/nomineedetails?access_token=...
     * JSON body: customer_id (required), scheme_id (optional when upstream expects it).
     */
    public function nomineeDetails(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'customer_id' => 'required|integer',
                'scheme_id' => 'nullable|integer',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Customer ID',
                'status' => 400,
            ], 400);
        }

        $path = (string) config(
            'thirdparty.mykalyan.nominee_details_path',
            'thirdparty/api/externals/nomineedetails'
        );
        $path = ltrim($path, '/');

        $payload = ['customer_id' => (int) $request->input('customer_id')];
        if ($request->filled('scheme_id')) {
            $payload['scheme_id'] = (int) $request->input('scheme_id');
        }

        try {
            $response = $this->thirdPartyApi->postWithAccessTokenInQuery($path, [], $payload);

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

    /**
     * Get Pincode Details – area, city, district, state by pincode.
     * POST /thirdparty/api/externals/get-pincode-details
     */
    public function getPincodeDetails(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'pincode' => 'required|string|max:50',
            ], [
                'pincode.required' => 'Pincode is required',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Pincode is required',
                'status' => 400,
            ], 400);
        }

        $pincode = $request->input('pincode');
        try {
            // Third-party requires access_token in query string.
            // Request body: { "pincode": 600106 }
            return response()->json($this->thirdPartyApi->postWithAccessTokenInQuery(
                self::THIRDPARTY_GET_PINCODE_DETAILS_PATH,
                [],
                ['pincode' => (int) $pincode]
            ));
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

    /**
     * Fetch gold rate from MyKalyan third-party API (same pattern as get-pincode-details).
     * Return null if Transaction_ID not unique or invalid (reserved for future idempotency checks).
     */
    private function fetchGoldRate(array $input): ?array
    {
        $path = (string) config('thirdparty.mykalyan.gold_rate_path', 'thirdparty/api/getstoregoldrate');
        $path = ltrim($path, '/');

        return $this->thirdPartyApi->postWithAccessTokenInQuery(
            $path,
            [],
            [
                'Date' => $input['Date'],
                'Region' => $input['Region'],
                'Location' => $input['Location'],
                'Transaction_ID' => $input['Transaction_ID'],
            ]
        );
    }

}
