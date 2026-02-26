<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Docman India API Configuration
    |--------------------------------------------------------------------------
    |
    | Credentials and base URL for Docman India login (POST /token). Token is
    | stored in documan_access_tokens; expires_at is always set to now + 1 day
    | when generated. Refresh is triggered when within 5 minutes of expiry.
    |
    */

    'base_url' => env('DOCUMAN_BASE_URL', ''),
    'token_path' => env('DOCUMAN_TOKEN_PATH', 'token'),
    'username' => env('DOCUMAN_USERNAME', ''),
    'password' => env('DOCUMAN_PASSWORD', ''),
    'token_ttl_days' => (int) env('DOCUMAN_TOKEN_TTL_DAYS', 1),
    'refresh_buffer_minutes' => (int) env('DOCUMAN_REFRESH_BUFFER_MINUTES', 5),
    'http_timeout_seconds' => (int) env('DOCUMAN_HTTP_TIMEOUT', 15),

    /*
    | Default token name for artisan and scheduler when no name is specified.
    */
    'default_token_name' => env('DOCUMAN_DEFAULT_TOKEN_NAME', 'default'),

];
