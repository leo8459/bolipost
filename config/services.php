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
        'base_url' => env('TRACKING_SQLSERVER_BASE_URL', env('TRACKING_API_URL')),
        'token' => env('TRACKING_SQLSERVER_TOKEN', env('TRACKING_API_TOKEN')),
    ],

    'solicitudes_sync' => [
        'base_url' => env('SOLICITUDES_SYNC_BASE_URL', 'https://gescon.correos.gob.bo:8459/'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'facturacion_bridge' => [
        'base_url' => env('FACTURACION_BRIDGE_BASE_URL'),
        'token' => env('FACTURACION_BRIDGE_TOKEN'),
        'nit_emisor' => env('FACTURACION_BRIDGE_NIT_EMISOR'),
        'fallback_email' => env('FACTURACION_BRIDGE_FALLBACK_EMAIL', 'sincorreo@agbc.bo'),
        'metodo_pago' => env('FACTURACION_BRIDGE_METODO_PAGO', 1),
        'formato_factura' => env('FACTURACION_BRIDGE_FORMATO_FACTURA', 'rollo'),
        'documento_sector' => env('FACTURACION_BRIDGE_DOCUMENTO_SECTOR', 1),
        'timeout' => env('FACTURACION_BRIDGE_TIMEOUT', 30),
        'ssl_verify' => env('FACTURACION_BRIDGE_SSL_VERIFY', true),
    ],

    'qhantuy_checkout' => [
        'base_url' => env('QHANTUY_CHECKOUT_BASE_URL', 'https://checkout.qhantuy.com/external-api/v2'),
        'token' => env('QHANTUY_CHECKOUT_TOKEN'),
        'appkey' => env('QHANTUY_CHECKOUT_APPKEY'),
        'callback_url' => env('QHANTUY_CHECKOUT_CALLBACK_URL'),
        'return_url' => env('QHANTUY_CHECKOUT_RETURN_URL'),
        'image_method' => env('QHANTUY_CHECKOUT_IMAGE_METHOD', 'URL'),
        'currency_code' => env('QHANTUY_CHECKOUT_CURRENCY_CODE', 'BOB'),
        'timeout' => env('QHANTUY_CHECKOUT_TIMEOUT', 30),
    ],

];
