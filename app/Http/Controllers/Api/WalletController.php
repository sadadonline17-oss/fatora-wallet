<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\WalletTopupRequest;
use App\Models\Wallet;
use App\Models\WalletTopup;
use App\Models\WalletTransaction;
use App\Services\PaymentGatewayFactory;
use App\Services\Gateways\KnetGatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class WalletController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $wallets = $user->wallets()->get();
        
        return response()->json([
            'success' => true,
            'data' => [
                'wallets' => $wallets->map(fn($wallet) => [
                    'id' => $wallet->id,
                    'currency' => $wallet->currency,
                    'balance' => (float) $wallet->balance,
                    'pending_balance' => (float) $wallet->pending_balance,
                    'available_balance' => (float) $wallet->available_balance,
                    'account_number' => $wallet->account_number,
                    'status' => $wallet->status,
                    'created_at' => $wallet->created_at,
                ]),
                'total_balance_usd' => $wallets->sum(fn($w) => $this->convertToUSD($w->balance, $w->currency)),
            ],
        ]);
    }

    public function show(Request $request, int $walletId): JsonResponse
    {
        $user = $request->user();
        
        $wallet = $user->wallets()->find($walletId);
        
        if (!$wallet) {
            return response()->json([
                'success' => false,
                'error' => 'Wallet not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'wallet' => [
                    'id' => $wallet->id,
                    'currency' => $wallet->currency,
                    'balance' => (float) $wallet->balance,
                    'pending_balance' => (float) $wallet->pending_balance,
                    'available_balance' => (float) $wallet->available_balance,
                    'account_number' => $wallet->account_number,
                    'status' => $wallet->status,
                    'last_transaction_at' => $wallet->last_transaction_at,
                    'created_at' => $wallet->created_at,
                ],
            ],
        ]);
    }

    public function topup(WalletTopupRequest $request): JsonResponse
    {
        $user = $request->user();
        $gateway = $request->input('gateway', 'knet');
        
        $supportedGateways = PaymentGatewayFactory::supported();
        if (!in_array($gateway, $supportedGateways)) {
            return response()->json([
                'success' => false,
                'error' => "Unsupported gateway. Supported: " . implode(', ', $supportedGateways),
            ], 400);
        }

        $wallet = $user->getOrCreateWallet($request->input('currency', 'KWD'));
        
        $amount = (float) $request->input('amount');
        
        if ($amount < 1) {
            return response()->json([
                'success' => false,
                'error' => 'Minimum topup amount is 1',
            ], 400);
        }

        $transactionId = WalletTopup::generateTransactionId();
        
        $topup = $wallet->topups()->create([
            'amount' => $amount,
            'currency' => $wallet->currency,
            'gateway' => $gateway,
            'transaction_id' => $transactionId,
            'fees' => 0,
            'net_amount' => $amount,
            'status' => WalletTopup::STATUS_PENDING,
        ]);

        try {
            $gatewayService = PaymentGatewayFactory::make($gateway);
            $gatewayService->initialize(config("payment.gateways.{$gateway}"));
            
            $metadata = [
                'user_id' => $user->id,
                'wallet_id' => $wallet->id,
                'topup_id' => $topup->id,
                'transaction_id' => $transactionId,
            ];
            
            $response = $gatewayService->createPayment($amount, $wallet->currency, $metadata);
            
            if ($response->isSuccessful()) {
                $topup->update([
                    'gateway_transaction_id' => $response->transactionId,
                    'payment_url' => $response->paymentUrl,
                    'metadata' => array_merge($topup->metadata ?? [], ['gateway_response' => $response->rawResponse]),
                ]);
                
                if ($gateway === 'knet') {
                    $knetForm = $gatewayService->generatePaymentForm($amount, $wallet->currency, $metadata);
                    $topup->update([
                        'metadata' => array_merge($topup->metadata ?? [], ['payment_form' => base64_encode($knetForm)]),
                    ]);
                }
                
                return response()->json([
                    'success' => true,
                    'data' => [
                        'topup_id' => $topup->id,
                        'transaction_id' => $transactionId,
                        'gateway_transaction_id' => $response->transactionId,
                        'payment_url' => $response->paymentUrl,
                        'amount' => $amount,
                        'currency' => $wallet->currency,
                        'fees' => $topup->fees,
                        'net_amount' => $topup->net_amount,
                        'status' => $topup->status,
                        'expires_at' => now()->addHours(24)->toIso8601String(),
                    ],
                ]);
            }
            
            $topup->fail($response->errorMessage ?? 'Payment creation failed');
            
            return response()->json([
                'success' => false,
                'error' => $response->errorMessage ?? 'Failed to create payment',
                'error_code' => $response->errorCode,
            ], 400);
            
        } catch (\Exception $e) {
            Log::error('Wallet topup failed', [
                'user_id' => $user->id,
                'wallet_id' => $wallet->id,
                'gateway' => $gateway,
                'error' => $e->getMessage(),
            ]);
            
            $topup->fail($e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => 'Payment processing failed',
            ], 500);
        }
    }

    public function topupStatus(Request $request, string $transactionId): JsonResponse
    {
        $user = $request->user();
        
        $topup = WalletTopup::whereHas('wallet', fn($q) => $q->where('user_id', $user->id))
            ->where('transaction_id', $transactionId)
            ->first();
        
        if (!$topup) {
            return response()->json([
                'success' => false,
                'error' => 'Transaction not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'topup_id' => $topup->id,
                'transaction_id' => $topup->transaction_id,
                'gateway_transaction_id' => $topup->gateway_transaction_id,
                'amount' => (float) $topup->amount,
                'currency' => $topup->currency,
                'fees' => (float) $topup->fees,
                'net_amount' => (float) $topup->net_amount,
                'status' => $topup->status,
                'paid_at' => $topup->paid_at?->toIso8601String(),
                'created_at' => $topup->created_at->toIso8601String(),
            ],
        ]);
    }

    public function transactions(Request $request): JsonResponse
    {
        $user = $request->user();
        $walletId = $request->input('wallet_id');
        $type = $request->input('type');
        $perPage = min($request->input('per_page', 20), 100);
        
        $query = $user->transactions();
        
        if ($walletId) {
            $wallet = $user->wallets()->find($walletId);
            if (!$wallet) {
                return response()->json(['success' => false, 'error' => 'Wallet not found'], 404);
            }
            $query->where('wallet_id', $walletId);
        }
        
        if ($type) {
            $query->where('type', $type);
        }
        
        $transactions = $query->orderByDesc('created_at')->paginate($perPage);
        
        return response()->json([
            'success' => true,
            'data' => [
                'transactions' => $transactions->map(fn($txn) => [
                    'id' => $txn->id,
                    'type' => $txn->type,
                    'amount' => (float) $txn->amount,
                    'balance_before' => (float) $txn->balance_before,
                    'balance_after' => (float) $txn->balance_after,
                    'description' => $txn->description,
                    'reference_id' => $txn->reference_id,
                    'status' => $txn->status,
                    'created_at' => $txn->created_at->toIso8601String(),
                ]),
                'pagination' => [
                    'current_page' => $transactions->currentPage(),
                    'last_page' => $transactions->lastPage(),
                    'per_page' => $transactions->perPage(),
                    'total' => $transactions->total(),
                ],
            ],
        ]);
    }

    public function knetCallback(Request $request): JsonResponse
    {
        Log::info('KNET callback received', $request->all());
        
        $knetResponse = $request->all();
        
        $knetService = new KnetGatewayService();
        $knetService->initialize(config('payment.gateways.knet'));
        
        $parsed = $knetService->parseKnetResponse($knetResponse);
        
        if (!$parsed['success']) {
            return response()->json([
                'success' => false,
                'message' => 'Payment failed',
                'result' => $parsed['result'] ?? 'FAILED',
            ]);
        }
        
        $transactionId = $parsed['transaction_id'];
        $topup = WalletTopup::where('gateway_transaction_id', $transactionId)
            ->where('status', '!=', WalletTopup::STATUS_COMPLETED)
            ->first();
        
        if (!$topup) {
            Log::warning('KNET callback: Topup not found', ['transaction_id' => $transactionId]);
            return response()->json(['success' => false, 'message' => 'Transaction not found']);
        }
        
        try {
            DB::beginTransaction();
            
            $topup->update([
                'gateway_transaction_id' => $parsed['transaction_id_knet'] ?? $transactionId,
                'metadata' => array_merge($topup->metadata ?? [], [
                    'knet_response' => $parsed,
                    'paid_at' => now()->toIso8601String(),
                ]),
            ]);
            
            $topup->confirm();
            
            DB::commit();
            
            Log::info('KNET payment confirmed', [
                'topup_id' => $topup->id,
                'transaction_id' => $transactionId,
                'amount' => $topup->amount,
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Payment successful',
                'result' => 'CAPTURED',
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('KNET callback processing failed', [
                'topup_id' => $topup->id,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Processing failed',
            ]);
        }
    }

    public function knetError(Request $request): JsonResponse
    {
        Log::warning('KNET error callback', $request->all());
        
        $transactionId = $request->input('track_id');
        
        $topup = WalletTopup::where('gateway_transaction_id', $transactionId)->first();
        
        if ($topup) {
            $topup->update([
                'metadata' => array_merge($topup->metadata ?? [], [
                    'error_response' => $request->all(),
                    'failed_at' => now()->toIso8601String(),
                ]),
            ]);
            
            if ($topup->status === WalletTopup::STATUS_PENDING) {
                $topup->cancel();
            }
        }
        
        return response()->redirectTo(config('app.frontend_url') . '/wallet?error=payment_failed');
    }

    public function paytabsCallback(Request $request): JsonResponse
    {
        Log::info('PayTabs callback received', $request->all());
        
        $tranRef = $request->input('tran_ref');
        $cartId = $request->input('cart_id');
        
        $topup = WalletTopup::where('transaction_id', $cartId)
            ->where('gateway', 'paytabs')
            ->first();
        
        if (!$topup) {
            return response()->json(['success' => false, 'message' => 'Transaction not found']);
        }
        
        try {
            $gateway = PaymentGatewayFactory::make('paytabs');
            $gateway->initialize(config('payment.gateways.paytabs'));
            
            $verification = $gateway->verifyPayment($cartId);
            
            if ($verification->isPaid()) {
                $topup->update([
                    'metadata' => array_merge($topup->metadata ?? [], [
                        'paytabs_response' => $verification->rawResponse,
                    ]),
                ]);
                $topup->confirm();
            }
            
            return response()->redirectTo(config('app.frontend_url') . '/wallet?status=success');
            
        } catch (\Exception $e) {
            Log::error('PayTabs callback failed', ['error' => $e->getMessage()]);
            return response()->redirectTo(config('app.frontend_url') . '/wallet?error=processing_failed');
        }
    }

    public function myfatoorahCallback(Request $request): JsonResponse
    {
        Log::info('MyFatoorah callback received', $request->all());
        
        $merchantReference = $request->input('MerchantReference');
        
        $topup = WalletTopup::where('transaction_id', $merchantReference)
            ->where('gateway', 'myfatoorah')
            ->first();
        
        if (!$topup) {
            return response()->json(['success' => false, 'message' => 'Transaction not found']);
        }
        
        try {
            $gateway = PaymentGatewayFactory::make('myfatoorah');
            $gateway->initialize(config('payment.gateways.myfatoorah'));
            
            $verification = $gateway->verifyPayment($merchantReference);
            
            if ($verification->isPaid()) {
                $topup->update([
                    'metadata' => array_merge($topup->metadata ?? [], [
                        'myfatoorah_response' => $verification->rawResponse,
                    ]),
                ]);
                $topup->confirm();
            }
            
            return response()->redirectTo(config('app.frontend_url') . '/wallet?status=success');
            
        } catch (\Exception $e) {
            Log::error('MyFatoorah callback failed', ['error' => $e->getMessage()]);
            return response()->redirectTo(config('app.frontend_url') . '/wallet?error=processing_failed');
        }
    }

    protected function convertToUSD(float $amount, string $currency): float
    {
        $rates = [
            'KWD' => 3.25,
            'SAR' => 3.75,
            'AED' => 3.67,
            'BHD' => 2.65,
            'EGP' => 48.50,
            'USD' => 1.00,
        ];
        
        $rate = $rates[$currency] ?? 1;
        return $amount / $rate;
    }
}
