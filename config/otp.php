<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OTP Configuration
    |--------------------------------------------------------------------------
    |
    | Control OTP expiry, rate limits, and verify attempts. All values
    | are read from .env; defaults are set here.
    |
    */

    'expiry_seconds' => (int) env('OTP_EXPIRY_SECONDS', 60),
    'max_requests' => (int) env('OTP_MAX_REQUESTS', 5),
    'max_verify_attempts' => (int) env('OTP_MAX_VERIFY_ATTEMPTS', 5),
    'resend_cooldown' => (int) env('OTP_RESEND_COOLDOWN', 60),

    /*
    |--------------------------------------------------------------------------
    | Qikberry SMS API
    |--------------------------------------------------------------------------
    |
    | Used for sending OTP SMS. Template content should contain {#numeric#}
    | which is replaced by the generated OTP.
    |
    */

    'qikberry' => [
        'base_url' => rtrim(env('QIKBERRY_BASE_URL', 'https://rest.qikberry.ai'), '/'),
        'api_key' => env('QIKBERRY_API_KEY', ''),
        'sender' => env('QIKBERRY_SENDER', 'KALJEW'),
        'template_id' => env('QIKBERRY_TEMPLATE_ID', '1107177217596841103'),
        'service' => env('QIKBERRY_SERVICE', 'T'),
        'template_message' => env(
            'QIKBERRY_TEMPLATE_MESSAGE',
            'OTP to login to Kalyan Jewellers App is {#numeric#}' . "\n" . 'Do not share this OTP with anyone. -Kalyan Jewellers'
        ),
    ],

];
