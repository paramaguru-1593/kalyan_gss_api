<?php

namespace App\Http\Controllers;

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

    // public function paymentResponse(Request $request)
    // {
    //     $msg = $request->msg;

    //     $data = explode('|',$msg);

    //     $referenceNo = $data[2];
    //     $bankRef = $data[3];
    //     $amount = $data[4];
    //     $bankId = $data[5];
    //     $authStatus = $data[14];

    //     $payment = SchemePayment::where(
    //         'billdesk_reference',
    //         $referenceNo
    //     )->first();

    //     if(!$payment){
    //         return "Invalid Payment";
    //     }

    //     $payment->bank_reference_no = $bankRef;
    //     $payment->bank_id = $bankId;
    //     $payment->gateway_response = $msg;

    //     if($authStatus == "0300")
    //     {

    //         $payment->status = "SUCCESS";
    //         $payment->payment_date = now();

    //         $payment->save();

    //         $enrollment = $payment->enrollment;

    //         $enrollment->paid_amount += $amount;

    //         $enrollment->pending_amount =
    //             $enrollment->installment_amount
    //             - $enrollment->paid_amount;

    //         if($enrollment->pending_amount <= 0){
    //             $enrollment->status = "COMPLETED";
    //         }

    //         $enrollment->save();

    //     }
    //     else
    //     {
    //         $payment->status = "FAILED";
    //         $payment->save();
    //     }

    //     $REDIRECT_API_URL = env('APP_FRONTEND_URL') . "/payment-result?status=" . $payment->status;

    //     return redirect($REDIRECT_API_URL);

    //     // return redirect(
    //     //     "https://uat-gss-api.kalyanm.in/payment-result?status=".$payment->status
    //     // );
    // }

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

        if($billDesk->authStatus === "0300")
        {
            $payment->status = "SUCCESS";
            $payment->payment_date = now();

            $payment->save();

            $enrollment = $payment->enrollment;

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

        // Send Java-like response data to frontend
        $redirectUrl = env('APP_FRONTEND_URL') . "/payment-result?status=" . $payment->status;

        return redirect($redirectUrl);
    }
}
