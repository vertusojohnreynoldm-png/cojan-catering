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

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model'   => env('ANTHROPIC_MODEL', 'claude-haiku-4-5-20251001'),
    ],

    'gcash' => [
        'number'     => env('GCASH_NUMBER'),
        'qr_payload' => env('GCASH_QR_PAYLOAD'),
    ],

    // Road routing for the rider map (App\Services\RouteService). Both
    // providers are free with no billing. ORS is used when ORS_API_KEY is
    // set; otherwise (or if ORS fails) the public OSRM server. If both fail
    // the map shows a straight dashed line with the as-the-crow-flies distance.
    'routing' => [
        'provider' => env('ROUTING_PROVIDER', 'auto'), // auto | ors | osrm
        'ors_key'  => env('ORS_API_KEY'),
        'osrm_url' => env('OSRM_URL', 'https://router.project-osrm.org'),
    ],

];
