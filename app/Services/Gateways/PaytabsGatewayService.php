<?php

namespace App\Services\Gateways;

use App\Services\PaymentGatewayInterface;
use App\Services\PaymentResponse;
use App\Services\VerificationResponse;
use App\Services\RefundResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class PaytabsGatewayService implements PaymentGatewayInterface
{
    protected string $merchantEmail;
    protected string $merchantSecretKey;
    protected string $currency;
    protected string $siteUrl;
    protected string $returnUrl;
    protected bool $testMode;
    protected string $profileId;

    public function initialize(array $config): void
    {
        $this->merchantEmail = $config['merchant_email'] ?? '';
        $this->merchantSecretKey = $config['secret_key'] ?? '';
        $this->currency = $config['currency'] ?? 'KWD';
        $this->siteUrl = rtrim($config['site_url'] ?? '', '/');
        $this->returnUrl = rtrim($config['return_url'] ?? '', '/');
        $this->testMode = $config['test_mode'] ?? false;
        $this->profileId = $config['profile_id'] ?? '';
    }

    public function createPayment(float $amount, string $currency, array $metadata = []): PaymentResponse
    {
        $transactionId = $metadata['transaction_id'] ?? 'TXN-' . time() . '-' . Str::random(8);
        
        $baseUrl = $this->testMode 
            ? 'https://secure.paytabs.sa' 
            : 'https://secure.paytabs.sa';
        
        $currencyCode = match ($currency) {
            'KWD' => 'KWD',
            'SAR' => 'SAR',
            'AED' => 'AED',
            'USD' => 'USD',
            'EGP' => 'EGP',
            default => 'KWD',
        };

        $postData = [
            'profile_id' => (int) $this->profileId,
            'tran_type' => 'sale',
            'tran_class' => 'ecom',
            'cart_id' => $transactionId,
            'cart_description' => $metadata['description'] ?? 'Wallet Topup',
            'cart_currency' => $currencyCode,
            'cart_amount' => round($amount, 2),
            'callback' => route('wallet.paytabs.callback'),
            'return' => route('wallet.paytabs.success'),
        ];

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post("{$baseUrl}/payment_request", $postData);

            $result = $response->json();

            Cache::put("paytabs_txn_{$transactionId}", [
                'transaction_id' => $transactionId,
                'amount' => $amount,
                'currency' => $currency,
                'metadata' => $metadata,
                'paytabs_tran_ref' => $result['tran_ref'] ?? null,
                'created_at' => now()->toIso8601String(),
            ], now()->addHours(24));

            if (isset($result['redirect_url'])) {
                return new PaymentResponse(
                    success: true,
                    transactionId: $transactionId,
                    paymentUrl: $result['redirect_url'],
                    rawResponse: $result
                );
            }

            return new PaymentResponse(
                success: false,
                transactionId: $transactionId,
                errorCode: $result['response_code'] ?? 'UNKNOWN_ERROR',
                errorMessage: $result['result'] ?? 'Payment creation failed',
                rawResponse: $result
            );
        } catch (\Exception $e) {
            return new PaymentResponse(
                success: false,
                errorCode: 'PAYTABS_EXCEPTION',
                errorMessage: $e->getMessage()
            );
        }
    }

    public function verifyPayment(string $transactionId): VerificationResponse
    {
        $storedData = Cache::get("paytabs_txn_{$transactionId}");
        
        if (!$storedData || !isset($storedData['paytabs_tran_ref'])) {
            return new VerificationResponse(
                success: false,
                errorCode: 'TRANSACTION_NOT_FOUND',
                errorMessage: 'Transaction not found'
            );
        }

        $baseUrl = $this->testMode 
            ? 'https://secure.paytabs.sa' 
            : 'https://secure.paytabs.sa';

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post("{$baseUrl}/verify", [
                'tran_ref' => $storedData['paytabs_tran_ref'],
            ]);

            $result = $response->json();

            if ($result['respStatus'] === 'A' || $result['respStatus'] === 'CAPTURED') {
                return new VerificationResponse(
                    success: true,
                    transactionId: $transactionId,
                    amount: (float) ($result['tran_amount'] ?? $storedData['amount']),
                    currency: $result['cart_currency'] ?? $storedData['currency'],
                    status: 'paid',
                    rawResponse: $result
                );
            }

            return new VerificationResponse(
                success: false,
                transactionId: $transactionId,
                status: $result['respStatus'] ?? 'failed',
                errorCode: $result['respCode'] ?? 'VERIFICATION_FAILED',
                errorMessage: $result['respMessage'] ?? 'Payment verification failed',
                rawResponse: $result
            );
        } catch (\Exception $e) {
            return new VerificationResponse(
                success: false,
                errorCode: 'PAYTABS_EXCEPTION',
                errorMessage: $e->getMessage()
            );
        }
    }

    public function refund(string $transactionId, float $amount): RefundResponse
    {
        return new RefundResponse(
            success: false,
            errorCode: 'NOT_IMPLEMENTED',
            errorMessage: 'PayTabs refund requires specific API endpoint'
        );
    }

    public function getPaymentUrl(string $transactionId): string
    {
        $storedData = Cache::get("paytabs_txn_{$transactionId}");
        return $storedData['redirect_url'] ?? '';
    }
}
