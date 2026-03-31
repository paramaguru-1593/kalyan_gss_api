<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerLedgerCollection;
use App\Models\CustomerSchemeDetail;
use App\Models\SchemeEnrollment;
use App\Models\SchemePayment;
use App\Services\BillDeskResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BillDeskPaymentController extends Controller
{

    public static function checksum($message,$key)
    {
        return strtoupper(hash_hmac('sha256',$message,$key));
    }

    public function createPayment(Request $request)
    {

        // $enrollment = SchemeEnrollment::findOrFail(
        //     $request->scheme_enrollment_id
        // );

        $enrollment = SchemeEnrollment::where('enrollment_id', $request->scheme_enrollment_id)->first();


        // $amount = $request->amount;
        $amount = 1;

        $mobile = $request->mobile;

        $currentDate = now()->format('dMYHis');

        $reference =
            $enrollment->enrollment_id.$mobile.'-'.$currentDate;

        $installmentNo =
            ($enrollment->paid_amount / $enrollment->installment_amount) + 1;

        $payment = SchemePayment::create([

            'scheme_enrollment_id'=>$enrollment->id,
            'billdesk_reference'=>$reference,
            'amount'=>$amount,
            'installment_no'=>$installmentNo,
            'status'=>'PENDING'

        ]);

        // Create current month ledger collection as pending if missing.
        $this->ensureCurrentMonthPendingCollection($enrollment, (float) $amount);

        $merchantId = env('BILLDESK_MERCHANT_ID');
        $securityId = env('BILLDESK_SECURITY_ID');

        $responseUrl = env('BILLDESK_RESPONSE_URL');

        $message =
            $merchantId."|".
            "".
            $reference.
            "|NA|".
            $amount.
            "|NA|NA|NA|INR|NA|R|".
            $securityId.
            "|NA|NA|F|NA|NA|NA|NA|NA|NA|NA|".
            $responseUrl;

        $checksum = $this->checksum(
            $message,
            env('BILLDESK_PAYMENT_KEY')
        );

        $msg = $message."|".$checksum;

        $redirect =
            env('BILLDESK_REDIRECT').$msg;

        return response()->json([
            "redirect_url"=>$redirect
        ]);

    }

    public function paymentResponse(Request $request)
    {
        $msg = $request->msg;

        // Parse like Java class
        $billDesk = new BillDeskResponse($msg);

        \Log::info("BillDesk Debug: " ,[
            'raw_msg' => $msg,
            'billDesk' => $billDesk
        ]);

        $payment = SchemePayment::where(
            'billdesk_reference',
            trim($billDesk->customerID)
        )->first();

        if(!$payment){
            return "Invalid Payment";
        }

        $payment->bank_reference_no = $billDesk->bankReferenceNo;
        $payment->bank_id = $billDesk->bankID;
        $payment->gateway_response = $msg;

        $enrollment = $payment->enrollment;

        if($billDesk->authStatus === "0300")
        {
            $payment->status = "SUCCESS";
            $payment->payment_date = now();

            $payment->save();

            $this->markCurrentMonthCollectionCompleted($enrollment, $payment, $billDesk);

            $enrollment->paid_amount += $billDesk->txnAmount;

            $enrollment->pending_amount =
                $enrollment->installment_amount
                - $enrollment->paid_amount;

            if($enrollment->pending_amount <= 0){
                $enrollment->status = "COMPLETED";
            }

            $enrollment->save();
        }
        else
        {
            $payment->status = "FAILED";
            $payment->save();
        }

        // Send Java-like response data to frontend with customerId
        $customerId = $enrollment?->customer_id;
        $query = http_build_query(array_filter([
            'status' => $payment->status,
            'refNo' => $payment->billdesk_reference,
        ], fn ($v) => $v !== null));

        $redirectUrl = env('APP_FRONTEND_URL') . "/payment-result?{$query}";

        return redirect($redirectUrl);
    }

    private function ensureCurrentMonthPendingCollection(SchemeEnrollment $enrollment, ?float $amount = null): void
    {
        $detail = $this->resolveOrCreateSchemeDetail($enrollment);
        $emiMonth = strtoupper(now()->format('M-Y'));

        $existing = CustomerLedgerCollection::query()
            ->where('customer_scheme_detail_id', $detail->id)
            ->where('emi_month', $emiMonth)
            ->first();

        if ($existing) {
            return;
        }

        CustomerLedgerCollection::create([
            'customer_scheme_detail_id' => $detail->id,
            'reference_no' => null,
            'mop' => null,
            'remarks' => 'Auto-created monthly entry',
            'collection_date' => null,
            'amount' => $amount ?? (float) $enrollment->installment_amount,
            'issued_date' => null,
            'cheque_number' => null,
            'emi_month' => $emiMonth,
            'payment_status' => 'pending',
            'gold_rate' => null,
            'gold_weight' => null,
        ]);
    }

    private function markCurrentMonthCollectionCompleted(
        SchemeEnrollment $enrollment,
        SchemePayment $payment,
        BillDeskResponse $billDesk
    ): void {
        $detail = $this->resolveOrCreateSchemeDetail($enrollment);
        $emiMonth = strtoupper(now()->format('M-Y'));
        $paidAmount = (float) ($billDesk->txnAmount ?? $payment->amount ?? 0);

        $collection = CustomerLedgerCollection::query()
            ->where('customer_scheme_detail_id', $detail->id)
            ->where('emi_month', $emiMonth)
            ->where(function ($query) {
                $query->whereNull('payment_status')
                    ->orWhereRaw('LOWER(payment_status) = ?', ['pending']);
            })
            ->orderByDesc('id')
            ->first();

        if (! $collection) {
            $collection = CustomerLedgerCollection::query()
                ->where('customer_scheme_detail_id', $detail->id)
                ->where('emi_month', $emiMonth)
                ->orderByDesc('id')
                ->first();
        }

        if (! $collection) {
            $collection = new CustomerLedgerCollection([
                'customer_scheme_detail_id' => $detail->id,
                'emi_month' => $emiMonth,
            ]);
        }

        $collection->reference_no = (string) ($payment->billdesk_reference ?? '');
        $collection->mop = 'Online';
        $collection->remarks = 'BillDesk payment success';
        $collection->collection_date = now()->toDateString();
        $collection->issued_date = now()->toDateString();
        $collection->amount = $paidAmount;
        $collection->payment_status = 'completed';
        $collection->save();
    }

    private function resolveOrCreateSchemeDetail(SchemeEnrollment $enrollment): CustomerSchemeDetail
    {
        return CustomerSchemeDetail::query()->firstOrCreate(
            ['enrollment_no' => (string) $enrollment->enrollment_id],
            [
                'customer_id' => $enrollment->customer_id,
                'scheme_enrollment_id' => $enrollment->id,
                'scheme_id' => $enrollment->scheme_id,
                'scheme_name' => $enrollment->scheme_name,
                'scheme_status' => $enrollment->status,
                'join_date' => $enrollment->enrollment_date,
                'maturity_date' => $enrollment->maturity_date,
                'emi' => (float) $enrollment->installment_amount,
                'paid_amount' => (float) $enrollment->paid_amount,
                'remaining_amount' => (float) $enrollment->pending_amount,
            ]
        );
    }

    public function paymentResponseDetails(Request $request)
    {
        $validated = $request->validate([
            'billdesk_reference' => ['nullable', 'string', 'max:255', 'required_without:customerId'],
            'customerId' => ['nullable', 'string', 'max:255', 'required_without:billdesk_reference'],
        ]);

        $paymentQuery = SchemePayment::query()->with('enrollment');

        if (! empty($validated['billdesk_reference'] ?? null)) {
            $paymentQuery->where('billdesk_reference', $validated['billdesk_reference']);
        } else {
            $customerId = $validated['customerId'];
            $paymentQuery->whereHas('enrollment', function ($q) use ($customerId) {
                $q->where('customer_id', $customerId);
            })
                ->orderByDesc('payment_date')
                ->orderByDesc('id');
        }

        $payment = $paymentQuery->first();

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Payment not found for the given reference/customer.',
            ], 404);
        }

        $billDesk = null;
        if (! empty($payment->gateway_response) && strtoupper((string) $payment->payment_gateway) === 'BILLDESK') {
            try {
                $billDesk = new BillDeskResponse($payment->gateway_response);
            } catch (\Throwable $e) {
                $billDesk = null;
            }
        }

        $txnDate = $billDesk?->txnDate ?? $payment->payment_date;

        $monthOfEmi = null;
        try {
            if ($txnDate) {
                $monthOfEmi = strtoupper(\Carbon\Carbon::parse($txnDate)->format('M-Y'));
            }
        } catch (\Throwable $e) {
            $monthOfEmi = null;
        }

        $enrollment = $payment->enrollment;

        return response()->json([
            'success' => $payment->status === 'SUCCESS',
            'message' => $payment->status === 'SUCCESS' ? 'Payment successful' : 'Payment status',
            'data' => [
                'scheme' => $enrollment?->scheme_name,
                'enrollmentId' => $enrollment?->enrollment_id,
                'monthOfEmi' => $monthOfEmi,
                'amount' => $billDesk?->txnAmount ?? $payment->amount,
                'transactionReference' => $billDesk?->txnReferenceNo ?? $payment->billdesk_reference,
                'bankReference' => $billDesk?->customerID ?? $payment->bank_reference_no,
                'transactionStatus' => $billDesk?->txnStatus ?? ($payment->status === 'SUCCESS' ? 'Successful Transaction' : 'Cancel Transaction'),
                'status' => $payment->status,
            ],
        ], 200);
    }

    /**
     * Transaction history for a customer (SUCCESS only).
     * POST /v1/payment/transactions/{customerId}
     */
    public function transactionHistory(Request $request)
    {
        $limit = (int) ($request->input('limit', $request->query('limit', 50)));
        if ($limit <= 0) {
            $limit = 50;
        }
        $limit = min($limit, 200);
        $customerId = Customer::where("customerId" ,$request->customerId)->value('id');

        $payments = SchemePayment::query()
            ->where('status', 'SUCCESS')
            ->whereHas('enrollment', function ($q) use ($customerId) {
                $q->where('customer_id', $customerId);
            })
            ->with('enrollment')
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $data = $payments->map(function (SchemePayment $payment) {
            $enrollment = $payment->enrollment;

            $billDesk = null;
            if (! empty($payment->gateway_response) && strtoupper((string) $payment->payment_gateway) === 'BILLDESK') {
                try {
                    $billDesk = new BillDeskResponse($payment->gateway_response);
                } catch (\Throwable $e) {
                    $billDesk = null;
                }
            }

            $paymentDate = $billDesk?->txnDate ?? $payment->payment_date;
            try {
                $paymentDate = $paymentDate ? \Carbon\Carbon::parse($paymentDate)->format('Y-m-d') : null;
            } catch (\Throwable $e) {
                $paymentDate = $payment->payment_date?->format('Y-m-d');
            }

            $gatewayLabel = match (strtoupper((string) $payment->payment_gateway)) {
                'BILLDESK' => 'Bill Desk',
                'RAZORPAY' => 'Razorpay',
                default => ucfirst(strtolower((string) $payment->payment_gateway)),
            };

            return [
                'scheme' => $enrollment?->scheme_name,
                'paymentGateway' => $gatewayLabel,
                'amount' => (string) ($billDesk?->txnAmount ?? $payment->amount),
                'status' => $payment->status,
                'statusLabel' => 'Completed',
                'paymentDate' => $paymentDate,
                'enrollmentNo' => $enrollment?->enrollment_id,
                'receiptId' => $payment->bank_reference_no
                    ?? $billDesk?->txnReferenceNo
                    ?? $payment->billdesk_reference,
                'paymentId' => $payment->id,
                'billdeskReference' => $payment->billdesk_reference,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
