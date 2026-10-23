<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Passport Guard
    |--------------------------------------------------------------------------
    |
    | Here you may specify which authentication guard Passport will use when
    | authenticating users. This value should correspond with one of your
    | guards that is already present in your "auth" configuration file.
    |
    */

    'guard' => 'web',

    'middleware' => [],

    /*
    |--------------------------------------------------------------------------
    | Encryption Keys
    |--------------------------------------------------------------------------
    |
    | Passport uses encryption keys while generating secure access tokens for
    | your application. By default, the keys are stored as local files but
    | can be set via environment variables when that is more convenient.
    |
    */

    'private_key' => env('PASSPORT_PRIVATE_KEY'),

    'public_key' => env('PASSPORT_PUBLIC_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Passport Database Connection
    |--------------------------------------------------------------------------
    |
    | By default, Passport's models will utilize your application's default
    | database connection. If you wish to use a different connection you
    | may specify the configured name of the database connection here.
    |
    */

    'connection' => env('PASSPORT_CONNECTION'),

    /*
    |--------------------------------------------------------------------------
    | Token Lifetimes (Achintha)
    |--------------------------------------------------------------------------
    |
    | Access tokens are short-lived, refresh tokens longer. Lifetimes live in
    | configuration, not in code.
    |
    */

    'token_lifetimes' => [
        'access_token_ttl_seconds' => (int) env('PASSPORT_ACCESS_TOKEN_TTL_SECONDS', 900),
        'refresh_token_ttl_days' => (int) env('PASSPORT_REFRESH_TOKEN_TTL_DAYS', 14),
    ],

    'access_token_ttl' => (int) env('PASSPORT_ACCESS_TOKEN_TTL_SECONDS', 900),

    'refresh_token_ttl' => (int) env('PASSPORT_REFRESH_TOKEN_TTL_DAYS', 14),

];
