<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'oauth_provider' => [
        'tenant_id' => env('OAUTH_TENANT_ID'),
        'grant_type' => env('OAUTH_GRANT_TYPE'),
        'client_id' => env('OAUTH_CLIENT_ID'),
        'client_secret' => env('OAUTH_CLIENT_SECRET'),
        'scope' => env('OAUTH_SCOPE'),
        'auth_url' => env('OAUTH_AUTH_URL'),
    ],

    'ihce' => [
        'base_url' => env('IHCE_BASE_URL'),
        'subscription_key' => env('IHCE_SUBSCRIPTION_KEY'),
        'timeout' => env('IHCE_TIMEOUT', 120),
    ],
    'siis' => [
        'base_url' => env('SIIS_BASE_URL'),
    ],
];
