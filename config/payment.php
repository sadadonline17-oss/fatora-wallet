<?php

return [
    'gateways' => [
        'knet' => [
            'enabled' => env('KNET_ENABLED', true),
            'merchant_id' => env('KNET_MERCHANT_ID', ''),
            'shared_secret' => env('KNET_SHARED_SECRET', ''),
            'module_id' => env('KNET_MODULE_ID', '1234'),
            'return_url' => env('KNET_RETURN_URL', env('APP_URL')),
            'error_url' => env('KNET_ERROR_URL', env('APP_URL')),
            'test_mode' => env('KNET_TEST_MODE', false),
            'fees' => [
                'type' => 'percentage',
                'value' => 2.0,
                'min' => 0.100,
                'max' => 10.000,
            ],
        ],
        
        'paytabs' => [
            'enabled' => env('PAYTABS_ENABLED', false),
            'merchant_email' => env('PAYTABS_MERCHANT_EMAIL', ''),
            'secret_key' => env('PAYTABS_SECRET_KEY', ''),
            'profile_id' => env('PAYTABS_PROFILE_ID', ''),
            'currency' => env('PAYTABS_CURRENCY', 'KWD'),
            'site_url' => env('PAYTABS_SITE_URL', env('APP_URL')),
            'return_url' => env('PAYTABS_RETURN_URL', env('APP_URL')),
            'test_mode' => env('PAYTABS_TEST_MODE', false),
            'fees' => [
                'type' => 'percentage',
                'value' => 2.5,
                'min' => 0.500,
                'max' => 15.000,
            ],
        ],
        
        'myfatoorah' => [
            'enabled' => env('MYFATOORAH_ENABLED', false),
            'api_key' => env('MYFATOORAH_API_KEY', ''),
            'country' => env('MYFATOORAH_COUNTRY', 'KWT'),
            'return_url' => env('MYFATOORAH_RETURN_URL', env('APP_URL')),
            'test_mode' => env('MYFATOORAH_TEST_MODE', false),
            'fees' => [
                'type' => 'percentage',
                'value' => 2.0,
                'min' => 0.250,
                'max' => 12.500,
            ],
        ],
    ],
    
    'currencies' => [
        'KWD' => [
            'code' => 'KWD',
            'name' => 'Kuwaiti Dinar',
            'symbol' => 'د.ك',
            'decimal_places' => 3,
            'min_amount' => 1.000,
            'max_amount' => 10000.000,
        ],
        'SAR' => [
            'code' => 'SAR',
            'name' => 'Saudi Riyal',
            'symbol' => 'ر.س',
            'decimal_places' => 2,
            'min_amount' => 10.00,
            'max_amount' => 50000.00,
        ],
        'AED' => [
            'code' => 'AED',
            'name' => 'UAE Dirham',
            'symbol' => 'د.إ',
            'decimal_places' => 2,
            'min_amount' => 10.00,
            'max_amount' => 50000.00,
        ],
        'USD' => [
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'decimal_places' => 2,
            'min_amount' => 5.00,
            'max_amount' => 100000.00,
        ],
    ],
    
    'default_gateway' => env('DEFAULT_PAYMENT_GATEWAY', 'knet'),
    
    'topup_limits' => [
        'min' => 1.000,
        'max' => 10000.000,
        'daily_limit' => 50000.000,
        'monthly_limit' => 200000.000,
    ],
];
