<?php

return [
    'fees' => [
        'knet' => 0.005,
        'emv' => 0.010,
        'card' => 0.025,
        'crypto' => 0.020,
        'withdrawal' => 0.005,
        'transfer' => 0.001,
        'payment' => 0.010,
        'refund' => 0,
    ],

    'min_fees' => [
        'knet' => 0.100,
        'emv' => 0.250,
        'card' => 0.500,
        'crypto' => 1.000,
        'withdrawal' => 0.100,
        'transfer' => 0.010,
        'payment' => 0.050,
        'refund' => 0,
    ],

    'max_fees' => [
        'knet' => 5.000,
        'emv' => 10.000,
        'card' => 25.000,
        'crypto' => 50.000,
        'withdrawal' => 10.000,
        'transfer' => 2.000,
        'payment' => 5.000,
        'refund' => 0,
    ],

    'knet' => [
        'merchant_id' => env('KNET_MERCHANT_ID', 'mock_merchant'),
        'username' => env('KNET_USERNAME', 'mock_user'),
        'password' => env('KNET_PASSWORD', 'mock_password'),
        'resource_key' => env('KNET_RESOURCE_KEY', 'mock_resource_key'),
        'action_url' => env('KNET_ACTION_URL', 'https://kpay.com.kw/payment'),
        'trans_portal' => env('KNET_TRANS_PORTAL', 'https://portal.knetpay.com.kw'),
        'is_test' => env('KNET_IS_TEST', true),
        'webhook_secret' => env('KNET_WEBHOOK_SECRET'),
    ],

    'emv' => [
        'terminal_id' => env('EMV_TERMINAL_ID', 'EMV001'),
        'terminal_key' => env('EMV_TERMINAL_KEY', 'demo_terminal_key'),
        'is_demo' => env('EMV_IS_DEMO', true),
        'webhook_secret' => env('EMV_WEBHOOK_SECRET'),
    ],

    'crypto' => [
        'api_endpoint' => env('CRYPTO_API_ENDPOINT', 'https://api.crypto-gateway.example/v1'),
        'api_key' => env('CRYPTO_API_KEY'),
        'webhook_secret' => env('CRYPTO_WEBHOOK_SECRET'),
        'is_test' => env('CRYPTO_IS_TEST', true),
        'supported_cryptos' => ['BTC', 'ETH', 'USDT', 'USDC'],
    ],

    'qr_code' => [
        'prefix' => env('QR_CODE_PREFIX', 'fatora://pay'),
        'secret' => env('QR_CODE_SECRET', 'default_qr_secret'),
    ],

    'withdrawal' => [
        '2fa_enabled' => env('WITHDRAWAL_2FA_ENABLED', true),
        'rate_limit' => env('WITHDRAWAL_RATE_LIMIT', 3),
        'rate_period' => env('WITHDRAWAL_RATE_PERIOD', 3600),
        'min_amount' => 1.000,
        'max_amount' => 5000.000,
    ],

    'topup' => [
        'min_amount' => 0.100,
        'max_amount' => 10000.000,
        'expiry_hours' => 24,
    ],

    'transfer' => [
        'min_amount' => 0.100,
        'max_amount' => 10000.000,
        'expiry_days' => 7,
    ],

    'limits' => [
        'daily_default' => 5000.000,
        'monthly_default' => 25000.000,
    ],

    'currencies' => [
        'KWD' => [
            'code' => '414',
            'name' => 'Kuwaiti Dinar',
            'symbol' => 'د.ك',
            'decimal_places' => 3,
        ],
        'USD' => [
            'code' => '840',
            'name' => 'US Dollar',
            'symbol' => '$',
            'decimal_places' => 2,
        ],
        'SAR' => [
            'code' => '682',
            'name' => 'Saudi Riyal',
            'symbol' => 'ر.س',
            'decimal_places' => 2,
        ],
    ],
];
