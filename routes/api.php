<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BillDeskPaymentController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DocumanController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\GoldRateController;
use App\Http\Controllers\OtpController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\SchemesController;
use App\Http\Controllers\SchemePaymentController;
use App\Http\Controllers\TermsController;

Route::post('/v1/login', [AuthController::class, 'login']);
Route::post('/payment/response', [BillDeskPaymentController::class,'paymentResponse']);

Route::group(['middleware' => 'auth:customer-api'], function () {
    Route::post('/v1/logout', [AuthController::class, 'logout']);

    // OTP: send and verify (rate limited, no auth required)
    Route::middleware('throttle:10,1')->group(function (): void {
        Route::post('/v1/otp/send', [OtpController::class, 'sendOtp']);
        Route::post('/v1/otp/verify', [OtpController::class, 'verifyOtp']);
    });

    // 
    Route::post('/v2/storebasedscheme_data', [SchemesController::class, 'storeBasedSchemeData']);
    Route::get('/v2/getSchemesByMobileNumber', [SchemesController::class, 'getSchemesByMobileNumber']);
    Route::get('/v2/getAccountInformation', [SchemesController::class, 'getAccountInformation']);
    Route::get('/v2/getCustomerLedgerReport', [SchemesController::class, 'getCustomerLedgerReport']);
    Route::get('/v2/getPaymentInformation', [PaymentController::class, 'getPaymentInformation']);
    Route::get('/v2/confirmPayment', [PaymentController::class, 'confirmPayment']);
    // 
    Route::post('/v2/customerkycinfo', [CustomerController::class, 'customerKycInfo']);
    Route::post('/v2/customerkycupdation', [CustomerController::class, 'customerKycUpdation']);
    Route::post('/v2/customerbankdetail_updation', [CustomerController::class, 'customerBankDetailUpdation']);
    Route::post('/v2/enroll_new', [EnrollmentController::class, 'enrollNew']);
    // 
    Route::post('/v2/get-pincode-details', [GoldRateController::class, 'getPincodeDetails']);
    Route::post('/v2/getstoregoldrate', [GoldRateController::class, 'getStoreGoldRate']);
    Route::get('/v2/gettermsandcondition', [TermsController::class, 'getTermsAndCondition']);
    Route::post('/v2/schemebenifits', [GoldRateController::class, 'schemeBenefits']);
    Route::post('/v2/nomineedetails', [GoldRateController::class, 'nomineeDetails']);

    Route::post('/update-personal-details', [CustomerController::class, 'updatePersonalDetails']);

    Route::post('/profile-completeness', [CustomerController::class, 'profileCompleteness']);
    
    // Docman India: GetCustomerDetails (separate API)
    Route::post('/customer/GetCustomerDetails', [DocumanController::class, 'getCustomerDetails']);

    Route::post('/payment/request', [BillDeskPaymentController::class,'createPayment']);
    Route::post('/v1/payment/response-details', [BillDeskPaymentController::class,'paymentResponseDetails']);
    Route::post('/v1/payment/transactions', [BillDeskPaymentController::class,'transactionHistory']);
    
    Route::post('/v1/payment/receipt-by-reference', [SchemePaymentController::class, 'getByBilldeskReference']);

});