<?php

namespace App\Services\Gateways;

use App\Models\Topup;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CryptoGateway implements PaymentGatewayInterface
{
    private array $supportedCryptos = ['BTC', 'ETH', 'USDT', 'USDC'];
    private string $apiEndpoint;
    private string $apiKey;
    private bool $isTest;

    public function __construct()
    {
        $this->apiEndpoint = config('fatora.crypto.api_endpoint', 'https://api.crypto-gateway.example/v1');
        $this->apiKey = config('fatora.crypto.api_key', '');
        $this->isTest = config('fatora.crypto.is_test', true);
    }

    public function createCheckout(Topup $topup): array
    {
        $orderId = 'CRYPTO' . time() . Str::random(4);
        $amount = $topup->amount;
        $currency = $topup->currency;

        $topup->update([
            'payment_id' => $orderId,
        ]);

        if ($this->isTest) {
            return $this->createMockCryptoCheckout($topup, $orderId);
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->apiEndpoint . '/orders', [
                'order_id' => $orderId,
                'amount' => $amount,
                'currency' => $currency,
                'callback_url' => route('api.webhooks.crypto'),
                'supported_cryptos' => $this->supportedCryptos,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'checkout_url' => $data['checkout_url'],
                    'payment_id' => $orderId,
                    'request' => $data,
                ];
            }

            return [
                'checkout_url' => null,
                'payment_id' => $orderId,
                'error' => 'Failed to create crypto checkout',
            ];
        } catch (\Exception $e) {
            Log::error('Crypto checkout failed', [
                'topup_id' => $topup->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'checkout_url' => null,
                'payment_id' => $orderId,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function verifyCallback(array $data): bool
    {
        if (!isset($data['signature']) || !isset($data['order_id'])) {
            return false;
        }

        $webhookSecret = config('fatora.crypto.webhook_secret', '');

        if (empty($webhookSecret)) {
            return true;
        }

        $signatureData = json_encode($data, JSON_UNESCAPED_UNICODE);
        $expectedSignature = hash_hmac('sha256', $signatureData, $webhookSecret);

        return hash_equals($expectedSignature, $data['signature']);
    }

    public function parseCallback(array $data): array
    {
        $status = $data['status'] ?? 'pending';
        $success = in_array($status, ['completed', 'confirmed', 'paid']);

        $cryptoAmount = $data['crypto_amount'] ?? null;
        $cryptoCurrency = $data['crypto_currency'] ?? null;

        return [
            'success' => $success,
            'order_id' => $data['order_id'] ?? null,
            'status' => $status,
            'crypto_amount' => $cryptoAmount,
            'crypto_currency' => $cryptoCurrency,
            'fiat_amount' => $data['fiat_amount'] ?? $data['amount'] ?? null,
            'fiat_currency' => $data['fiat_currency'] ?? $data['currency'] ?? null,
            'tx_hash' => $data['tx_hash'] ?? $data['transaction_hash'] ?? null,
            'confirmations' => $data['confirmations'] ?? 0,
            'result_message' => $this->getStatusMessage($status),
        ];
    }

    public function getCheckoutUrl(Topup $topup): string
    {
        return $this->apiEndpoint . '/pay/' . $topup->payment_id;
    }

    public function refund(string $transactionRef, float $amount): array
    {
        $refundId = 'CRYPTO_REF' . time() . Str::random(4);

        if ($this->isTest) {
            return [
                'success' => true,
                'refund_id' => $refundId,
                'message' => 'Crypto refund successful (mock)',
            ];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])->post($this->apiEndpoint . '/refunds', [
                'order_id' => $transactionRef,
                'refund_id' => $refundId,
                'amount' => $amount,
            ]);

            $result = $response->json();

            return [
                'success' => $result['success'] ?? false,
                'refund_id' => $refundId,
                'message' => $result['message'] ?? 'Refund processed',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'refund_id' => $refundId,
                'message' => $e->getMessage(),
            ];
        }
    }

    private function createMockCryptoCheckout(Topup $topup, string $orderId): array
    {
        $btcRate = 0.0000105;
        $ethRate = 0.000158;
        $usdtRate = 0.304;

        return [
            'checkout_url' => route('api.webhooks.crypto.mock', [
                'topup_id' => $topup->id,
                'order_id' => $orderId,
            ]),
            'payment_id' => $orderId,
            'crypto_addresses' => [
                'BTC' => [
                    'address' => 'bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh',
                    'amount' => round($topup->amount * $btcRate, 8),
                    'qr' => 'bitcoin:bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh?amount=' . round($topup->amount * $btcRate, 8),
                ],
                'ETH' => [
                    'address' => '0x742d35Cc6634C0532925a3b844Bc9e7595f2bD13',
                    'amount' => round($topup->amount * $ethRate, 8),
                    'qr' => 'ethereum:0x742d35Cc6634C0532925a3b844Bc9e7595f2bD13?amount=' . round($topup->amount * $ethRate, 8),
                ],
                'USDT' => [
                    'address' => '0x742d35Cc6634C0532925a3b844Bc9e7595f2bD13',
                    'amount' => round($topup->amount * $usdtRate, 2),
                    'network' => 'ERC20',
                ],
            ],
            'request' => [
                'order_id' => $orderId,
                'fiat_amount' => $topup->amount,
                'fiat_currency' => $topup->currency,
                'mode' => 'TEST',
            ],
            'demo_notice' => 'This is a demo. Use the mock endpoint to simulate payment.',
        ];
    }

    private function getStatusMessage(string $status): string
    {
        $messages = [
            'pending' => 'Awaiting cryptocurrency payment',
            'processing' => 'Processing blockchain transaction',
            'completed' => 'Payment confirmed',
            'confirmed' => 'Payment confirmed with required confirmations',
            'paid' => 'Payment received',
            'expired' => 'Payment window expired',
            'failed' => 'Payment failed',
        ];

        return $messages[$status] ?? "Status: {$status}";
    }

    public function getSupportedCryptos(): array
    {
        return $this->supportedCryptos;
    }
}
