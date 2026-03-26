<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'name' => 'Fatora Wallet API',
        'version' => '1.0.0',
        'documentation' => '/api/docs',
    ]);
});

Route::get('/docs', function () {
    return response()->json([
        'openapi' => '3.0.0',
        'info' => [
            'title' => 'Fatora Wallet API',
            'version' => '1.0.0',
            'description' => 'Kuwait Payment Gateway API - KNET, EMV, Crypto Support',
        ],
        'servers' => [
            ['url' => config('app.url'), 'description' => 'Local'],
        ],
    ]);
});
