<?php

return [
    'throttle' => [
        'api' => [
            'prefix' => 'api',
            'maxAttempts' => 60,
            'decayMinutes' => 1,
            'by' => 'request.ip',
        ],
        'auth' => [
            'prefix' => 'auth',
            'maxAttempts' => 5,
            'decayMinutes' => 15,
            'by' => 'request.ip',
        ],
        'topup' => [
            'prefix' => 'topup',
            'maxAttempts' => 10,
            'decayMinutes' => 5,
            'by' => 'auth.user',
        ],
        'transfer' => [
            'prefix' => 'transfer',
            'maxAttempts' => 5,
            'decayMinutes' => 10,
            'by' => 'auth.user',
        ],
    ],
];
