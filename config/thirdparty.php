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
    ],

];
