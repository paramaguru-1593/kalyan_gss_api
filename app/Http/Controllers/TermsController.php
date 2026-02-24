<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TermsController extends Controller
{
    /**
     * Get terms and conditions for a scheme.
     * POST /thirdparty/api/externals/gettermsandcondition
     * Body: { "scheme_id": "1035" }
     * Header: content-type: application/json, access_token (required in spec, not validated).
     */
    public function getTermsAndCondition(Request $request): JsonResponse
    {
        $request->validate([
            'scheme_id' => 'required|string|max:255',
        ], [
            'scheme_id.required' => 'scheme_id is required',
        ]);

        $schemeId = $request->input('scheme_id');

        $content = $this->findTermsBySchemeId($schemeId);

        if ($content === null) {
            return response()->json([
                'message' => 'Invalid Scheme ID',
                'status' => 400,
            ], 400);
        }

        return response()->json([
            'data' => $content,
        ]);
    }

    /**
     * Find terms and conditions content for the given scheme ID. Return null if invalid.
     */
    private function findTermsBySchemeId(string $schemeId): ?string
    {
        $schemeId = trim($schemeId);
        if ($schemeId === '') {
            return null;
        }

        // TODO: Replace with DB or config lookup per scheme_id.
        // Stub: return sample T&C for known scheme IDs (e.g. 1035, 1001), else null.
        $terms = $this->getDefaultTermsContent();

        return $terms;
    }

    /**
     * Default terms content (matches sample response).
     */
    private function getDefaultTermsContent(): string
    {
        return <<<'HTML'
            <b>Terms & Conditions</b>
            Kalyan Jeweller's Payment Portal Equals Integration Document
            ~1. You have enrolled in the Kalyan Akshaya Scheme. You may opt for any amount as per your convenience for enrolling into the Kalyan Akshaya Scheme, subject to a minimum amount of Rs 1000/-.
            ~2. For the 1st instalment, you only need to pay 40% of the amount opted for by you. The subsequent instalments (2nd instalment to the 11th instalment) should be for the full amount.
            ~3. You are eligible to purchase jewellery for the amount remitted by you at the end of 11 months. Gold coins cannot be purchased under this scheme.
            ~4. The monthly installment cannot be carried over or paid in advance.
            ~5. You can pay the monthly installments in any of the Kalyan Jewellers showrooms in India, My Kalyan Mini Stores and Online at payments.kalyanjewellers.net.
            ~6. One compulsory payment in every month is to be made for 11 months.
            HTML;
    }
}
