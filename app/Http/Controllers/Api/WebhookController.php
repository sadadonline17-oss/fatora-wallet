<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Topup;
use App\Models\WebhookLog;
use App\Services\PaymentGatewayFactory;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function __construct(
        private WalletService $walletService,
        private PaymentGatewayFactory $gatewayFactory
    ) {}

    public function knet(Request $request): JsonResponse
    {
        $log = $this->logWebhook('knet', 'knet_callback', $request);

        try {
            $trackId = $request->get('track_id') ?? $request->get('TrackId');

            $topup = Topup::where('track_id', $trackId)->first();

            if (!$topup) {
                $log->markAsFailed('Topup not found');
                return response()->json(['success' => false, 'message' => 'Not found'], 404);
            }

            $log->update(['payload' => $request->all()]);

            $success = $this->walletService->processTopupCallback($topup, $request->all());

            if ($success) {
                $log->markAsVerified();
                $log->markAsProcessed('Topup completed successfully');
            } else {
                $log->markAsFailed('Processing failed');
            }

            return response()->json([
                'success' => $success,
                'message' => $success ? 'Payment processed' : 'Processing failed',
            ]);
        } catch (\Exception $e) {
            $log->markAsFailed($e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error'], 500);
        }
    }

    public function knetMock(Request $request, int $topupId, string $trackId): JsonResponse
    {
        $topup = Topup::findOrFail($topupId);

        if ($topup->status !== Topup::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'Topup already processed',
            ]);
        }

        $mockResponse = [
            'track_id' => $trackId,
            'auth_code' => 'MOCK_AUTH_' . rand(1000, 9999),
            'result_code' => '00',
            'result_message' => 'Transaction Approved',
            'reference' => 'REF_' . rand(100000, 999999),
            'amount' => number_format($topup->amount, 3, '.', ''),
            'signature' => 'mock_signature',
        ];

        $topup->update(['response_payload' => $mockResponse]);

        $this->walletService->processTopupCallback($topup, $mockResponse);

        return response()->json([
            'success' => true,
            'message' => 'Mock payment successful',
            'topup' => [
                'uuid' => $topup->uuid,
                'status' => $topup->fresh()->status,
                'amount' => $topup->amount,
                'currency' => $topup->currency,
            ],
            'wallet_balance' => $topup->wallet->balance,
        ]);
    }

    public function knetError(Request $request): JsonResponse
    {
        $this->logWebhook('knet', 'knet_error', $request);

        return response()->json([
            'success' => false,
            'message' => 'Payment failed or cancelled',
            'error_code' => $request->get('result_code'),
        ]);
    }

    public function emv(Request $request): JsonResponse
    {
        $log = $this->logWebhook('emv', 'emv_callback', $request);

        try {
            $transactionId = $request->get('transaction_id') ?? $request->get('transactionId');

            $topup = Topup::where('payment_id', $transactionId)->first();

            if (!$topup) {
                $log->markAsFailed('Topup not found');
                return response()->json(['success' => false, 'message' => 'Not found'], 404);
            }

            $log->update(['payload' => $request->all()]);

            $gateway = $this->gatewayFactory->make('emv');

            if (!$gateway->verifyCallback($request->all())) {
                $log->markAsFailed('Invalid signature');
                return response()->json(['success' => false, 'message' => 'Invalid signature'], 401);
            }

            $result = $gateway->parseCallback($request->all());

            if ($result['success']) {
                $topup->markAsCompleted($result['auth_code'] ?? null, $result['transaction_id'] ?? null);
                $topup->wallet->addBalance($topup->net_amount);
                $log->markAsVerified();
                $log->markAsProcessed('EMV payment completed');
            } else {
                $topup->markAsFailed($result['result_code'], $result['result_message']);
                $log->markAsFailed($result['result_message']);
            }

            return response()->json([
                'success' => $result['success'],
                'message' => $result['result_message'],
            ]);
        } catch (\Exception $e) {
            $log->markAsFailed($e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error'], 500);
        }
    }

    public function emvMock(Request $request, int $topupId, string $transactionId): JsonResponse
    {
        $topup = Topup::findOrFail($topupId);

        if ($topup->status !== Topup::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'Topup already processed',
            ]);
        }

        $mockResponse = [
            'transaction_id' => $transactionId,
            'auth_code' => 'EMV_AUTH_' . rand(1000, 9999),
            'response_code' => '00',
            'card_last4' => '4242',
            'terminal_id' => config('fatora.emv.terminal_id'),
            'signature' => 'mock_signature',
        ];

        $topup->update(['response_payload' => $mockResponse]);

        $gateway = $this->gatewayFactory->make('emv');

        if ($gateway->verifyCallback($mockResponse)) {
            $topup->markAsCompleted($mockResponse['auth_code'], $mockResponse['transaction_id']);
            $topup->wallet->addBalance($topup->net_amount);

            return response()->json([
                'success' => true,
                'message' => 'Mock EMV payment successful',
                'topup' => [
                    'uuid' => $topup->uuid,
                    'status' => $topup->fresh()->status,
                    'auth_code' => $mockResponse['auth_code'],
                ],
                'wallet_balance' => $topup->wallet->balance,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Mock signature verification failed',
        ]);
    }

    public function crypto(Request $request): JsonResponse
    {
        $log = $this->logWebhook('crypto', 'crypto_callback', $request);

        try {
            $orderId = $request->get('order_id');

            $topup = Topup::where('payment_id', $orderId)->first();

            if (!$topup) {
                $log->markAsFailed('Topup not found');
                return response()->json(['success' => false, 'message' => 'Not found'], 404);
            }

            $log->update(['payload' => $request->all()]);

            $gateway = $this->gatewayFactory->make('crypto');

            if (!$gateway->verifyCallback($request->all())) {
                $log->markAsFailed('Invalid signature');
                return response()->json(['success' => false, 'message' => 'Invalid signature'], 401);
            }

            $result = $gateway->parseCallback($request->all());

            if ($result['success']) {
                $topup->markAsCompleted();
                $topup->wallet->addBalance($topup->net_amount);
                $topup->update([
                    'metadata' => [
                        'crypto_amount' => $result['crypto_amount'],
                        'crypto_currency' => $result['crypto_currency'],
                        'tx_hash' => $result['tx_hash'] ?? null,
                    ],
                ]);
                $log->markAsVerified();
                $log->markAsProcessed('Crypto payment completed');
            } else {
                $topup->markAsFailed('crypto', $result['result_message']);
                $log->markAsFailed($result['result_message']);
            }

            return response()->json([
                'success' => $result['success'],
                'message' => $result['result_message'],
            ]);
        } catch (\Exception $e) {
            $log->markAsFailed($e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error'], 500);
        }
    }

    public function cryptoMock(Request $request, int $topupId, string $orderId): JsonResponse
    {
        $topup = Topup::findOrFail($topupId);

        if ($topup->status !== Topup::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'Topup already processed',
            ]);
        }

        $mockResponse = [
            'order_id' => $orderId,
            'status' => 'completed',
            'crypto_amount' => '0.001234',
            'crypto_currency' => 'BTC',
            'tx_hash' => '0x' . bin2hex(random_bytes(32)),
            'confirmations' => 6,
        ];

        $topup->update(['response_payload' => $mockResponse]);

        $topup->markAsCompleted();
        $topup->wallet->addBalance($topup->net_amount);

        return response()->json([
            'success' => true,
            'message' => 'Mock crypto payment successful',
            'topup' => [
                'uuid' => $topup->uuid,
                'status' => $topup->fresh()->status,
            ],
            'wallet_balance' => $topup->wallet->balance,
        ]);
    }

    public function mockComplete(Request $request, int $topupId, string $mockId): JsonResponse
    {
        $topup = Topup::findOrFail($topupId);

        if ($topup->status !== Topup::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'Topup already processed',
            ]);
        }

        $topup->markAsCompleted($mockId, $mockId);
        $topup->wallet->addBalance($topup->net_amount);

        return response()->json([
            'success' => true,
            'message' => 'Mock payment successful',
            'data' => [
                'topup_uuid' => $topup->uuid,
                'status' => $topup->fresh()->status,
                'new_balance' => $topup->wallet->balance,
            ],
        ]);
    }

    private function logWebhook(string $provider, string $eventType, Request $request): WebhookLog
    {
        return WebhookLog::create([
            'provider' => $provider,
            'event_type' => $eventType,
            'headers' => $request->headers->all(),
            'payload' => $request->all(),
            'signature' => $request->header('X-Signature') ?? $request->header('Signature'),
            'process_status' => WebhookLog::PROCESS_PENDING,
        ]);
    }
}
