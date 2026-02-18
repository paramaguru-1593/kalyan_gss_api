<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\KycController;
use App\Http\Controllers\GoldRateController;

Route::get('/', function () {
    return view('welcome');
});

// Third-party KYC / bank updation APIs
Route::post('thirdparty/api/customerkycupdation', [KycController::class, 'customerKycUpdation']);
Route::post('thirdparty/api/customerbankdetail_updation', [KycController::class, 'customerBankDetailUpdation']);

// Gold rate, scheme benefits, nominee details, pincode
Route::post('thirdparty/api/getstoregoldrate', [GoldRateController::class, 'getStoreGoldRate']);
Route::post('thirdparty/api/externals/schemebenifits', [GoldRateController::class, 'schemeBenefits']);
Route::post('thirdparty/api/externals/nomineedetails', [GoldRateController::class, 'nomineeDetails']);
Route::post('thirdparty/api/externals/get-pincode-details', [GoldRateController::class, 'getPincodeDetails']);