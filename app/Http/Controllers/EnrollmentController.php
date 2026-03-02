<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Scheme;
use App\Models\SchemeEnrollment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
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
        return DB::transaction(function () use ($data): ?array {
            // Find existing customer by external customerId; do not create a new customer here.
            $externalCustomerId = (int) $data['customer_id'];
            $customer = Customer::where('customerId', $externalCustomerId)->first();

            if (! $customer) {
                // Customer must exist before creating an enrollment.
                return null;
            }

            $tenure = (int) $data['tenure'];
            $emiAmount = (float) $data['emi_amount'];

            $joinDate = Carbon::now();
            $maturityDate = $tenure > 0 ? $joinDate->copy()->addMonths($tenure) : null;
            $pendingAmount = $tenure > 0 ? $emiAmount * $tenure : 0.0;

            // Generate a unique enrollment_id for this enrollment (account number).
            $enrollmentId = null;
            do {
                $candidate = (string) (90000000000000 + random_int(1, 9_999_999));
            } while (SchemeEnrollment::where('enrollment_id', $candidate)->exists());
            $enrollmentId = $candidate;

            $schemeName = Scheme::where('id', $data['scheme_id'])
                ->value('scheme_name'); // Optional: can be set based on scheme_id if needed

            $enrollment = new SchemeEnrollment([
                'customer_id' => $customer->id,
                'scheme_id' => (int) $data['scheme_id'],
                'scheme_name' => $schemeName,
                'enrollment_id' => $enrollmentId,
                'enrollment_date' => $joinDate->toDateString(),
                'maturity_date' => $maturityDate ? $maturityDate->toDateString() : null,
                'installment_amount' => $emiAmount,
                'paid_amount' => 0.0,
                'pending_amount' => $pendingAmount,
                'status' => 'Open',
            ]);

            $enrollment->save();
            $customer->refreshTotalEnrollments();

            return [
                'account_no' => $enrollmentId,
                'receipt_no' => 20000 + $enrollment->id,
            ];
        });
    }

    private function normalizeMobile(string $value): string
    {
        return trim(preg_replace('/\s+/', '', $value));
    }
}
