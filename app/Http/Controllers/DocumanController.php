<?php

namespace App\Http\Controllers;

use App\Exceptions\DocumanApiException;
use App\Services\DocumanApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Docman India API: customer/GetCustomerDetails.
 * POST body: MobileNo (required), optional DocumentType, DocumentNumber.
 */
class DocumanController extends Controller
{
    public function __construct(
        private readonly DocumanApiService $documanApiService
    ) {
    }

    /**
     * Get customer details by MobileNo or DocumentType + DocumentNumber.
     * Response: Docman format { StatusCode, Message, Data }.
     */
    public function getCustomerDetails(Request $request): JsonResponse
    {
        $request->validate([
            'MobileNo' => 'required|string|max:50',
            'DocumentType' => 'nullable|string|max:64',
            'DocumentNumber' => 'nullable|string|max:64',
        ]);

        // At least one of: MobileNo, or (DocumentType + DocumentNumber)
        $body = array_filter([
            'MobileNo' => $request->input('MobileNo'),
            'DocumentType' => $request->input('DocumentType'),
            'DocumentNumber' => $request->input('DocumentNumber'),
        ]);

        if ($body === []) {
            return response()->json([
                'StatusCode' => 400,
                'Message' => 'Provide MobileNo or DocumentType and DocumentNumber.',
                'Data' => [],
            ], 400);
        }

        try {
            $response = $this->documanApiService->getCustomerDetails($body);

            return response()->json($response);
        } catch (DocumanApiException $e) {
            Log::warning('Docman GetCustomerDetails failed', [
                'body' => $body,
                'message' => $e->getMessage(),
                'http_status' => $e->getHttpStatus(),
            ]);

            return response()->json([
                'StatusCode' => $e->getHttpStatus() ?? 502,
                'Message' => $e->getMessage(),
                'Data' => [],
            ], $e->getHttpStatus() ?? 502);
        }
    }
}
