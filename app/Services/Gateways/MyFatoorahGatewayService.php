<?php

namespace App\Services\Gateways;

use App\Services\PaymentGatewayInterface;
use App\Services\PaymentResponse;
use App\Services\VerificationResponse;
use App\Services\RefundResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class MyFatoorahGatewayService implements PaymentGatewayInterface
{
    protected string $apiKey;
    protected string $countryIsoCode;
    protected string $returnUrl;
    protected bool $testMode;

    public function initialize(array $config): void
    {
        $this->apiKey = $config['api_key'] ?? '';
        $this->countryIsoCode = $config['country'] ?? 'KWT';
        $this->returnUrl = rtrim($config['return_url'] ?? '', '/');
        $this->testMode = $config['test_mode'] ?? false;
    }

    protected function getBaseUrl(): string
    {
        $country = strtoupper($this->countryIsoCode);
        $testPrefix = $this->testMode ? 'apitest.' : 'api.';
        
        return match ($country) {
            'SAU', 'SA' => "https://{$testPrefix}myfatoorah.com",
            'KWT', 'KW' => "https://{$testPrefix}myfatoorah.com",
            'BHR', 'BH' => "https://{$testPrefix}myfatoorah.com",
            'ARE', 'AE' => "https://{$testPrefix}myfatoorah.com",
            default => "https://{$testPrefix}myfatoorah.com",
        };
    }

    protected function getCountryCode(): string
    {
        return match (strtoupper($this->countryIsoCode)) {
            'SAU', 'SA' => 'SAU',
            'KWT', 'KW' => 'KWT',
            'BHR', 'BH' => 'BHR',
            'ARE', 'AE' => 'ARE',
            'EGY', 'EG' => 'EGY',
            default => 'KWT',
        };
    }

    public function createPayment(float $amount, string $currency, array $metadata = []): PaymentResponse
    {
        $transactionId = $metadata['transaction_id'] ?? 'TXN-' . time() . '-' . Str::random(8);
        
        $currencyCode = match ($currency) {
            'SAR' => 'SAR',
            'KWD' => 'KWD',
            'BHD' => 'BHD',
            'AED' => 'AED',
            'EGP' => 'EGP',
            'USD' => 'USD',
            default => 'KWD',
        };

        $invoiceItems = [];
        if (isset($metadata['items'])) {
            foreach ($metadata['items'] as $item) {
                $invoiceItems[] = [
                    'ItemName' => $item['name'] ?? 'Wallet Topup',
                    'Quantity' => $item['quantity'] ?? 1,
                    'UnitPrice' => $item['price'] ?? $amount,
                ];
            }
        } else {
            $invoiceItems[] = [
                'ItemName' => 'Wallet Topup',
                'Quantity' => 1,
                'UnitPrice' => $amount,
            ];
        }

        $postData = [
            'InvoiceAmount' => round($amount, 2),
            'CurrencyCode' => $currencyCode,
            'CustomerName' => $metadata['customer_name'] ?? 'Wallet User',
            'CustomerEmail' => $metadata['customer_email'] ?? '',
            'CustomerMobile' => $metadata['customer_mobile'] ?? '',
            'InvoiceItems' => $invoiceItems,
            'CallBackUrl' => $this->returnUrl . '/api/wallet/myfatoorah/callback',
            'ErrorUrl' => $this->returnUrl . '/api/wallet/myfatoorah/error',
            'MerchantReference' => $transactionId,
        ];

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])->post($this->getBaseUrl() . '/v2/ExecutePayment', $postData);

            $result = $response->json();

            Cache::put("myfatoorah_txn_{$transactionId}", [
                'transaction_id' => $transactionId,
                'amount' => $amount,
                'currency' => $currency,
                'metadata' => $metadata,
                'invoice_id' => $result['Data']['InvoiceId'] ?? null,
                'payment_url' => $result['Data']['PaymentUrl'] ?? null,
                'created_at' => now()->toIso8601String(),
            ], now()->addHours(24));

            if (isset($result['Data']['PaymentUrl'])) {
                return new PaymentResponse(
                    success: true,
                    transactionId: $transactionId,
                    paymentUrl: $result['Data']['PaymentUrl'],
                    rawResponse: $result
                );
            }

            return new PaymentResponse(
                success: false,
                transactionId: $transactionId,
                errorCode: $result['ErrorCode'] ?? 'UNKNOWN_ERROR',
                errorMessage: $result['Message'] ?? 'Payment creation failed',
                rawResponse: $result
            );
        } catch (\Exception $e) {
            return new PaymentResponse(
                success: false,
                errorCode: 'MYFATOORAH_EXCEPTION',
                errorMessage: $e->getMessage()
            );
        }
    }

    public function verifyPayment(string $transactionId): VerificationResponse
    {
        $storedData = Cache::get("myfatoorah_txn_{$transactionId}");
        
        if (!$storedData || !isset($storedData['invoice_id'])) {
            return new VerificationResponse(
                success: false,
                errorCode: 'TRANSACTION_NOT_FOUND',
                errorMessage: 'Transaction not found'
            );
        }

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])->get($this->getBaseUrl() . '/v2/GetPaymentStatus', [
                'KeyType' => 'InvoiceId',
                'Key' => $storedData['invoice_id'],
            ]);

            $result = $response->json();

            if (isset($result['Data'])) {
                $data = $result['Data'];
                $invoiceStatus = $data['InvoiceStatus'] ?? '';
                
                $isPaid = in_array($invoiceStatus, ['Paid', 'Succss', 'APPROVED']);

                return new VerificationResponse(
                    success: $isPaid,
                    transactionId: $transactionId,
                    amount: (float) ($data['InvoiceAmount'] ?? $storedData['amount']),
                    currency: $data['CurrencyCode'] ?? $storedData['currency'],
                    status: $invoiceStatus,
                    rawResponse: $result
                );
            }

            return new VerificationResponse(
                success: false,
                transactionId: $transactionId,
                errorCode: $result['ErrorCode'] ?? 'VERIFICATION_FAILED',
                errorMessage: $result['Message'] ?? 'Verification failed',
                rawResponse: $result
            );
        } catch (\Exception $e) {
            return new VerificationResponse(
                success: false,
                errorCode: 'MYFATOORAH_EXCEPTION',
                errorMessage: $e->getMessage()
            );
        }
    }

    public function refund(string $transactionId, float $amount): RefundResponse
    {
        $storedData = Cache::get("myfatoorah_txn_{$transactionId}");
        
        if (!$storedData || !isset($storedData['invoice_id'])) {
            return new RefundResponse(
                success: false,
                errorCode: 'TRANSACTION_NOT_FOUND',
                errorMessage: 'Transaction not found'
            );
        }

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])->post($this->getBaseUrl() . '/v2/MakeRefund', [
                'InvoiceId' => $storedData['invoice_id'],
                'Amount' => round($amount, 2),
            ]);

            $result = $response->json();

            if (isset($result['Data'])) {
                return new RefundResponse(
                    success: true,
                    refundId: $result['Data']['RefundId'] ?? null,
                    amount: $amount,
                    status: 'completed',
                    rawResponse: $result
                );
            }

            return new RefundResponse(
                success: false,
                errorCode: $result['ErrorCode'] ?? 'REFUND_FAILED',
                errorMessage: $result['Message'] ?? 'Refund failed',
                rawResponse: $result
            );
        } catch (\Exception $e) {
            return new RefundResponse(
                success: false,
                errorCode: 'MYFATOORAH_EXCEPTION',
                errorMessage: $e->getMessage()
            );
        }
    }

    public function getPaymentUrl(string $transactionId): string
    {
        $storedData = Cache::get("myfatoorah_txn_{$transactionId}");
        return $storedData['payment_url'] ?? '';
    }
}
