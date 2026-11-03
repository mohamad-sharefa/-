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

    // Separate inventory service (Node.js) reached over gRPC; see proto/inventory/v1/inventory.proto
    'inventory' => [
        'driver' => env('INVENTORY_DRIVER', 'grpc'), // "memory" in tests
        'host' => env('INVENTORY_GRPC_HOST', 'inventory:50051'),
        // Every call gives up after this long, so a slow or stopped service never blocks the user
        'timeout_ms' => (int) env('INVENTORY_TIMEOUT_MS', 2000),
    ],

];
