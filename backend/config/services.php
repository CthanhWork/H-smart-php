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
        'mode' => env('VNPAY_MODE', 'gateway'),
        'frontend_url' => env('FRONTEND_URL', 'http://localhost:5174'),
        'tmn_code' => env('VNPAY_TMN_CODE'),
        'hash_secret' => env('VNPAY_HASH_SECRET'),
        'payment_url' => env('VNPAY_PAY_URL', env('VNPAY_PAYMENT_URL', 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html')),
        'return_url' => env('VNPAY_RETURN_URL'),
        'ipn_url' => env('VNPAY_IPN_URL'),
        'wallet_return_url' => env('VNPAY_WALLET_RETURN_URL'),
        'wallet_result_url' => env('VNPAY_WALLET_RESULT_URL') ?: rtrim(env('FRONTEND_URL', 'http://localhost:5174'), '/').'/me/wallet',
        'result_redirect_url' => env('VNPAY_RESULT_REDIRECT_URL') ?: rtrim(env('FRONTEND_URL', 'http://localhost:5174'), '/').'/payment-result',
        'sandbox_return_fallback' => (bool) env('VNPAY_SANDBOX_RETURN_FALLBACK', false),
    ],

    'marketplace' => [
        'commission_rate' => (int) env('MARKETPLACE_COMMISSION_RATE', 2),
        'shipping_fee' => (int) env('MARKETPLACE_SHIPPING_FEE', 25000),
        'listing_free_monthly' => (int) env('MARKETPLACE_LISTING_FREE_MONTHLY', 5),
        'listing_fee' => (int) env('MARKETPLACE_LISTING_FEE', 5000),
        'boost_price' => (int) env('MARKETPLACE_BOOST_PRICE', 10000),
        'boost_days' => (int) env('MARKETPLACE_BOOST_DAYS', 3),
        'vip_price' => (int) env('MARKETPLACE_VIP_PRICE', 15000),
        'vip_days' => (int) env('MARKETPLACE_VIP_DAYS', 7),
        'promotion_slots' => (int) env('MARKETPLACE_PROMOTION_SLOTS', 3),
        'cod_deposit_percent' => (int) env('MARKETPLACE_COD_DEPOSIT_PERCENT', 25),
        'policy_version' => env('MARKETPLACE_POLICY_VERSION', 'commerce-v1'),
    ],
    'wallet' => [
        'minimum_withdrawal' => (int) env('WALLET_MINIMUM_WITHDRAWAL', 50000),
        'maximum_withdrawal' => (int) env('WALLET_MAXIMUM_WITHDRAWAL', 10000000),
    ],
];
