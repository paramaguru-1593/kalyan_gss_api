<?php

namespace App\Http\Controllers;

use App\Exceptions\ThirdPartyApiException;
use App\Services\ThirdPartyApiService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class TermsController extends Controller
{
    public function __construct(
        private readonly ThirdPartyApiService $thirdPartyApi,
    ) {
    }

    /**
     * Get terms and conditions for a scheme via MyKalyan externals API.
     * POST /api/v2/gettermsandcondition
     */
    public function getTermsAndCondition(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'scheme_id' => 'required|string|max:255',
            ], [
                'scheme_id.required' => 'scheme_id is required',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Invalid Scheme ID',
                'status' => 400,
            ], 400);
        }

        $path = (string) config(
            'thirdparty.mykalyan.terms_and_condition_path',
            'thirdparty/api/externals/gettermsandcondition'
        );
        $path = ltrim($path, '/');

        try {
            $response = $this->thirdPartyApi->postWithAccessTokenInQuery($path, [], [
                'scheme_id' => (string) $request->input('scheme_id'),
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
}
