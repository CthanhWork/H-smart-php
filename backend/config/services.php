<?php

return [
    'ai' => [
        'url' => env('AI_SERVICE_URL', 'http://ai:8000'),
        'timeout' => (int) env('AI_SERVICE_TIMEOUT', 25),
        'vision_timeout' => (int) env('AI_VISION_SERVICE_TIMEOUT', 40),
        'description_timeout' => (int) env('AI_DESCRIPTION_SERVICE_TIMEOUT', 65),
    ],

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    'vnpay' => [
        'tmn_code' => env('VNPAY_TMN_CODE'),
        'hash_secret' => env('VNPAY_HASH_SECRET'),
        'payment_url' => env('VNPAY_PAY_URL', env('VNPAY_PAYMENT_URL', 'https://pay.vnpay.vn/vpcpay.html')),
        'return_url' => env('VNPAY_RETURN_URL'),
        'ipn_url' => env('VNPAY_IPN_URL'),
        'result_redirect_url' => env('VNPAY_RESULT_REDIRECT_URL', env('FRONTEND_URL', 'http://localhost:5173').'/me/orders'),
        'sandbox_return_fallback' => (bool) env('VNPAY_SANDBOX_RETURN_FALLBACK', false),
    ],

];
