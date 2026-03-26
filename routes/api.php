<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\WalletController;

Route::prefix('v1')->group(function () {
    
    Route::middleware(['auth:sanctum', 'verified'])->group(function () {
        
        Route::prefix('wallet')->group(function () {
            Route::get('/', [WalletController::class, 'index']);
            Route::get('/{walletId}', [WalletController::class, 'show']);
            Route::post('/topup', [WalletController::class, 'topup']);
            Route::get('/topup/{transactionId}/status', [WalletController::class, 'topupStatus']);
            Route::get('/transactions', [WalletController::class, 'transactions']);
        });
    });
    
    Route::prefix('wallet/webhook')->group(function () {
        Route::post('/knet', [WalletController::class, 'knetCallback']);
        Route::post('/knet/error', [WalletController::class, 'knetError']);
        Route::post('/paytabs', [WalletController::class, 'paytabsCallback']);
        Route::post('/myfatoorah', [WalletController::class, 'myfatoorahCallback']);
    });
    
    Route::get('/knet/payment-form/{transactionId}', function ($transactionId) {
        $topup = \App\Models\WalletTopup::where('transaction_id', $transactionId)->first();
        
        if (!$topup || !isset($topup->metadata['payment_form'])) {
            abort(404);
        }
        
        $form = base64_decode($topup->metadata['payment_form']);
        
        return response($form)
            ->header('Content-Type', 'text/html');
    });
});

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
        'services' => [
            'database' => \DB::connection()->getPdo() ? 'connected' : 'disconnected',
            'redis' => \Illuminate\Support\Facades\Cache::store('redis')->get('test') !== null ? 'connected' : 'disconnected',
        ],
    ]);
});
