<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third-Party API (MyKalyan) Configuration
    |--------------------------------------------------------------------------
    |
    | Credentials and base URL for the third-party login and API. Token is
    | stored in DB and refreshed when expired or within buffer_seconds (default
    | 5 minutes so token is refreshed before expiry). Set THIRDPARTY_TOKEN_BUFFER_SECONDS
    | in .env to override. A scheduled task runs every minute to refresh the token.
    | Staging defaults are set below; override THIRDPARTY_USERNAME and
    | THIRDPARTY_PASSWORD in .env for production or different environments.
    |
    */

    'mykalyan' => [
        'base_url' => env('THIRDPARTY_BASE_URL', 'https://staging.mykalyan.company'),
        'login_path' => env('THIRDPARTY_LOGIN_PATH', '/thirdparty/api/Users/login'),
        'username' => env('THIRDPARTY_USERNAME', 'onlineindia_user'),
        'password' => env('THIRDPARTY_PASSWORD', 'bVUBRydd'),
        'token_name' => env('THIRDPARTY_TOKEN_NAME', 'mykalyan'),
        'buffer_seconds' => (int) env('THIRDPARTY_TOKEN_BUFFER_SECONDS', 300),
        'lock_seconds' => (int) env('THIRDPARTY_LOCK_SECONDS', 30),
        /** POST body: Date, Region, Location, Transaction_ID; access_token in query. */
        'gold_rate_path' => env('THIRDPARTY_GOLD_RATE_PATH', 'thirdparty/api/getstoregoldrate'),
        /** POST body: customer_id (and optional scheme_id); access_token in query. */
        'nominee_details_path' => env('THIRDPARTY_NOMINEE_DETAILS_PATH', 'thirdparty/api/externals/nomineedetails'),
        /** POST body: scheme_id; access_token in query. */
        'terms_and_condition_path' => env(
            'THIRDPARTY_TERMS_AND_CONDITION_PATH',
            'thirdparty/api/externals/gettermsandcondition'
        ),
        /** POST body: scheme_id; access_token in query. */
        'scheme_benefits_path' => env('THIRDPARTY_SCHEME_BENEFITS_PATH', 'thirdparty/api/externals/schemebenifits'),
        /** Enrollment creation; access_token in query. */
        'enroll_new_path' => env('THIRDPARTY_ENROLL_NEW_PATH', 'thirdparty/api/enroll_new'),
        /** GET EnrollmentID + access_token in query. */
        'get_payment_information_path' => env('THIRDPARTY_GET_PAYMENT_INFORMATION_PATH', 'thirdparty/api/Enrollment_tbs/getPaymentInformation'),
        /** GET Date, enrNo, amount, transId, email, channel + access_token in query (after gateway success). */
        'confirm_collection_payment_path' => env(
            'THIRDPARTY_CONFIRM_COLLECTION_PAYMENT_PATH',
            'thirdparty/api/Collection_tbs/confirmPayment'
        ),
        /** When customer email is missing, used as enrNo collection confirm email (max 100). */
        'collection_fallback_email' => env('THIRDPARTY_COLLECTION_FALLBACK_EMAIL', ''),
        /** Value sent as channel (payment gateway name). */
        'collection_payment_channel' => env('THIRDPARTY_COLLECTION_PAYMENT_CHANNEL', 'BillDesk'),
    ],

];
