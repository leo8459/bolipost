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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
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

    'tracking_sqlserver' => [
        'base_url' => env('TRACKING_SQLSERVER_BASE_URL'),
        'token' => env('TRACKING_SQLSERVER_TOKEN'),
    ],

    'solicitudes_sync' => [
        'base_url' => env('SOLICITUDES_SYNC_BASE_URL', 'https://gescon.correos.gob.bo:8459/'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'whatsapp_alerts' => [
        'enabled' => (bool) env('WHATSAPP_ALERTS_ENABLED', false),
        'webhook_url' => env('WHATSAPP_ALERTS_WEBHOOK_URL'),
        'webhook_token' => env('WHATSAPP_ALERTS_WEBHOOK_TOKEN'),
        'recipients' => array_values(array_filter(array_map(
            static fn ($item) => trim((string) $item),
            explode(',', (string) env('WHATSAPP_ALERTS_RECIPIENTS', ''))
        ))),
        'timeout_seconds' => (int) env('WHATSAPP_ALERTS_TIMEOUT_SECONDS', 12),
        'min_interval_minutes' => (int) env('WHATSAPP_ALERTS_MIN_INTERVAL_MINUTES', 10),
    ],

];
