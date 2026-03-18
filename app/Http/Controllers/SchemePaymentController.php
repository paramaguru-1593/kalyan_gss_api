<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\SchemePayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SchemePaymentController extends Controller
{
    /**
     * Payment / bond receipt details by BillDesk order reference.
     * POST JSON: { "billdesk_reference": "..." }
     */
    public function getByBilldeskReference(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'billdesk_reference' => ['required', 'string', 'max:255'],
        ]);

        $payment = SchemePayment::query()
            ->where('billdesk_reference', $validated['billdesk_reference'])
            ->with(['enrollment.customer'])
            ->first();

        if ($payment === null || $payment->enrollment === null) {
            return response()->json([
                'success' => false,
                'message' => 'Payment not found for this billdesk_reference.',
            ], 404);
        }

        $enrollment = $payment->enrollment;
        $customer = $enrollment->customer;

        return response()->json([
            'success' => true,
            'data' => [
                'company_name' => 'Kalyan Jewellers India Limited',
                'scheme_name' => $enrollment->scheme_name,
                'customer_id' => $customer?->customerId ?? $customer?->id,
                'name_of_customer' => $customer?->customer_name ?? $customer?->full_name,
                'date_of_birth' => $customer?->date_of_birth?->format('Y-m-d'),
                'gender' => $customer?->gender,
                'mobile_no' => $customer?->mobile_no,
                'email_id' => $customer?->email,
                'address' => $this->formatCustomerAddress($customer),
                'enrollment_id' => $enrollment->enrollment_id,
                'month_of_emi' => $payment->installment_no,
                'amount' => (string) $payment->amount,
                'amount_formatted' => '₹' . number_format((float) $payment->amount, 2),
                'transaction_reference' => $payment->billdesk_reference,
                'transaction_status' => $this->mapTransactionStatus($payment->status),
                'mode_of_payment' => 'Online',
                'bank_reference_no' => $payment->bank_reference_no,
                'payment_gateway' => $payment->payment_gateway,
                'payment_date' => $payment->payment_date?->toIso8601String(),
                'id_proof' => $this->formatIdProof($customer),
                'scheme_payment' => [
                    'id' => $payment->id,
                    'status' => $payment->status,
                    'installment_no' => $payment->installment_no,
                ],
            ],
        ]);
    }

    private function formatCustomerAddress(?Customer $customer): ?string
    {
        if ($customer === null) {
            return null;
        }
        if (! empty($customer->address)) {
            return $customer->address;
        }
        $parts = array_filter([
            $customer->current_house_no,
            $customer->current_street,
            $customer->current_city,
            $customer->current_state,
            $customer->current_pincode,
        ]);

        return $parts !== [] ? implode(', ', $parts) : null;
    }

    private function mapTransactionStatus(string $status): string
    {
        return match (strtoupper($status)) {
            'SUCCESS' => 'Completed',
            'PENDING' => 'Pending',
            default => ucfirst(strtolower($status)),
        };
    }

    private function formatIdProof(?Customer $customer): string
    {
        if ($customer === null) {
            return 'NO ID PROOF AVAILABLE';
        }
        $hasProof = ! empty($customer->id_proof_number)
            || ! empty($customer->id_proof_front_side_url);
        if (! $hasProof) {
            return 'NO ID PROOF AVAILABLE';
        }

        return trim((string) ($customer->id_proof_number ?? 'ID proof on file'));
    }
}
