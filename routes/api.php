<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DocumanController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\GoldRateController;
use App\Http\Controllers\KycController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\SchemesController;
use App\Http\Controllers\TermsController;

Route::post('/v1/login', [AuthController::class, 'login']);

Route::group(['middleware' => 'auth:customer-api'], function () {
    Route::post('/v1/logout', [AuthController::class, 'logout']);

    Route::post('/update-personal-details', [CustomerController::class, 'updatePersonalDetails']);
    Route::post('/customerkycupdation', [CustomerController::class, 'customerKycUpdation']);
    Route::post('/customerbankdetail_updation', [CustomerController::class, 'customerBankDetailUpdation']);

    Route::post('/profile-completeness', [CustomerController::class, 'profileCompleteness']);

    // External APIs (no authentication required)
    Route::get('/externals/getSchemesByMobileNumber', [SchemesController::class, 'getSchemesByMobileNumber']);

    Route::get('/externals/gettermsandcondition', [TermsController::class, 'getTermsAndCondition']);

    Route::post('/enroll_new', [EnrollmentController::class, 'enrollNew']);

    Route::post('/customerkycinfo', [CustomerController::class, 'customerKycInfo']);

    // Docman India: GetCustomerDetails (separate API)
    Route::post('/customer/GetCustomerDetails', [DocumanController::class, 'getCustomerDetails']);

    // Enrollment / account information
    Route::get('/Enrollment_tbs/getAccountInformation', [SchemesController::class, 'getAccountInformation']);
    Route::get('/Enrollment_tbs/getPaymentInformation', [PaymentController::class, 'getPaymentInformation']);

    // Collection creation (confirm payment)
    Route::get('/Collection_tbs/confirmPayment', [PaymentController::class, 'confirmPayment']);

    // Scheme list (store-based) and customer ledger
    Route::post('/storebasedscheme_data', [SchemesController::class, 'storeBasedSchemeData']);
    Route::get('/externals/getCustomerLedgerReport', [SchemesController::class, 'getCustomerLedgerReport']);

    // Third-party KYC / bank updation APIs
    // Route::post('/customerkycupdation', [KycController::class, 'customerKycUpdation']);
    // Route::post('/customerbankdetail_updation', [KycController::class, 'customerBankDetailUpdation']);


    // Gold rate, scheme benefits, nominee details,pincode
    Route::post('/getstoregoldrate', [GoldRateController::class, 'getStoreGoldRate']);
    Route::post('/externals/schemebenifits', [GoldRateController::class, 'schemeBenefits']);
    Route::post('/externals/nomineedetails', [GoldRateController::class, 'nomineeDetails']);
    Route::post('/externals/get-pincode-details', [GoldRateController::class, 'getPincodeDetails']);
});