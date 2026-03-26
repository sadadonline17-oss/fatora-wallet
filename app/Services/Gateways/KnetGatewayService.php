<?php

namespace App\Services\Gateways;

use App\Services\PaymentGatewayInterface;
use App\Services\PaymentResponse;
use App\Services\VerificationResponse;
use App\Services\RefundResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class KnetGatewayService implements PaymentGatewayInterface
{
    protected string $merchantId;
    protected string $sharedSecret;
    protected string $moduleId;
    protected string $returnUrl;
    protected string $errorUrl;
    protected bool $testMode;

    public function initialize(array $config): void
    {
        $this->merchantId = $config['merchant_id'] ?? '';
        $this->sharedSecret = $config['shared_secret'] ?? '';
        $this->moduleId = $config['module_id'] ?? '1234';
        $this->returnUrl = rtrim($config['return_url'] ?? '', '/');
        $this->errorUrl = rtrim($config['error_url'] ?? '', '/');
        $this->testMode = $config['test_mode'] ?? false;
    }

    public function createPayment(float $amount, string $currency, array $metadata = []): PaymentResponse
    {
        $transactionId = $metadata['transaction_id'] ?? 'TXN-' . time() . '-' . Str::random(8);
        
        $currencyCode = match ($currency) {
            'KWD' => '414',
            'USD' => '840',
            'SAR' => '682',
            'AED' => '784',
            default => '414',
        };

        $amountFormatted = number_format($amount, 3, '.', '');
        
        $trackId = $transactionId;
        $postDate = date('d/m/Y H:i:s');
        
        $resourcePath = "/merchant/{$this->merchantId}/payment";
        $accessPoint = $this->testMode ? 'https://knet.kpay.com.kw' : 'https://kpay.com.kw';
        $userAgent = 'KNet/1.0';
        
        $stringToSign = $this->merchantId . $trackId . $amountFormatted . $currencyCode . 
                        $this->sharedSecret;
        
        $action = '1';
        $langid = 'AR';
        $responseUrl = $this->returnUrl . '/api/wallet/knet/callback';
        $errorUrl = $this->errorUrl . '/api/wallet/knet/error';
        
        try {
            $client = new \GuzzleHttp\Client([
                'base_uri' => $accessPoint,
                'timeout' => 30,
            ]);

            $formParams = [
                'amp;action' => $action,
                'amp;merchant_id' => $this->merchantId,
                'amp;track_id' => $trackId,
                'amp;amount' => $amountFormatted,
                'amp;currency_code' => $currencyCode,
                'amp;response_url' => $responseUrl,
                'amp;error_url' => $errorUrl,
                'amp;langid' => $langid,
                'amp;udf1' => $transactionId,
                'amp;udf2' => json_encode($metadata),
                'amp;udf3' => '',
                'amp;udf4' => '',
                'amp;udf5' => '',
            ];

            $headers = [
                'User-Agent' => $userAgent,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ];

            $response = $client->post($resourcePath, [
                'form_params' => $formParams,
                'headers' => $headers,
            ]);

            $body = (string) $response->getBody();
            
            Cache::put("knet_txn_{$transactionId}", [
                'track_id' => $trackId,
                'amount' => $amount,
                'currency' => $currency,
                'metadata' => $metadata,
                'created_at' => now()->toIso8601String(),
            ], now()->addHours(24));

            if (str_contains($body, 'Error')) {
                return new PaymentResponse(
                    success: false,
                    errorCode: 'KNET_ERROR',
                    errorMessage: $body,
                    rawResponse: ['response' => $body]
                );
            }

            return new PaymentResponse(
                success: true,
                transactionId: $trackId,
                paymentUrl: $accessPoint . $resourcePath,
                rawResponse: [
                    'response' => $body,
                    'form_action' => $accessPoint . $resourcePath,
                    'form_params' => $formParams,
                ]
            );
        } catch (\Exception $e) {
            return new PaymentResponse(
                success: false,
                errorCode: 'KNET_EXCEPTION',
                errorMessage: $e->getMessage(),
                rawResponse: ['exception' => $e->getMessage()]
            );
        }
    }

    public function verifyPayment(string $transactionId): VerificationResponse
    {
        $storedData = Cache::get("knet_txn_{$transactionId}");
        
        if (!$storedData) {
            return new VerificationResponse(
                success: false,
                errorCode: 'TRANSACTION_NOT_FOUND',
                errorMessage: 'Transaction not found or expired'
            );
        }

        return new VerificationResponse(
            success: true,
            transactionId: $transactionId,
            amount: $storedData['amount'],
            currency: $storedData['currency'],
            status: 'pending',
            rawResponse: $storedData
        );
    }

    public function refund(string $transactionId, float $amount): RefundResponse
    {
        return new RefundResponse(
            success: false,
            errorCode: 'NOT_IMPLEMENTED',
            errorMessage: 'KNET refund requires manual processing or specific API integration'
        );
    }

    public function getPaymentUrl(string $transactionId): string
    {
        $accessPoint = $this->testMode ? 'https://kpaytest.com.kw' : 'https://kpay.com.kw';
        return $accessPoint . "/merchant/{$this->merchantId}/payment";
    }

    public function generatePaymentForm(float $amount, string $currency, array $metadata = []): string
    {
        $response = $this->createPayment($amount, $currency, $metadata);
        
        if (!$response->isSuccessful()) {
            throw new \RuntimeException($response->errorMessage ?? 'Failed to create payment');
        }

        $transactionId = $response->transactionId;
        $currencyCode = match ($currency) {
            'KWD' => '414',
            'USD' => '840',
            'SAR' => '682',
            'AED' => '784',
            default => '414',
        };

        $amountFormatted = number_format($amount, 3, '.', '');
        
        $form = sprintf(
            '<form id="knet_payment_form" action="%s/merchant/%s/payment" method="POST">
                <input type="hidden" name="action" value="1">
                <input type="hidden" name="merchant_id" value="%s">
                <input type="hidden" name="track_id" value="%s">
                <input type="hidden" name="amount" value="%s">
                <input type="hidden" name="currency_code" value="%s">
                <input type="hidden" name="response_url" value="%s/api/wallet/knet/callback">
                <input type="hidden" name="error_url" value="%s/api/wallet/knet/error">
                <input type="hidden" name="langid" value="AR">
                <input type="hidden" name="udf1" value="%s">
                <input type="hidden" name="udf2" value="%s">
            </form>',
            $this->testMode ? 'https://kpaytest.com.kw' : 'https://kpay.com.kw',
            $this->merchantId,
            $this->merchantId,
            $transactionId,
            $amountFormatted,
            $currencyCode,
            config('app.url'),
            config('app.url'),
            $transactionId,
            json_encode($metadata)
        );

        return $form;
    }

    public function verifyKnetSignature(array $response): bool
    {
        if (!isset($response['Result']) || !isset($response['AuthCode']) || !isset($response['PostDate'])) {
            return false;
        }

        if ($response['Result'] !== 'CAPTURED') {
            return false;
        }

        return true;
    }

    public function parseKnetResponse(array $knetResponse): array
    {
        return [
            'success' => isset($knetResponse['Result']) && $knetResponse['Result'] === 'CAPTURED',
            'transaction_id' => $knetResponse['TrackId'] ?? null,
            'auth_code' => $knetResponse['AuthCode'] ?? null,
            'reference' => $knetResponse['Ref'] ?? null,
            'result' => $knetResponse['Result'] ?? null,
            'result_code' => $knetResponse['ResultCode'] ?? null,
            'post_date' => $knetResponse['PostDate'] ?? null,
            'transaction_id_knet' => $knetResponse['TransactionId'] ?? null,
            'amount' => isset($knetResponse['Amt']) ? (float) $knetResponse['Amt'] : null,
            'currency' => isset($knetResponse['Currency']) ? $this->mapCurrencyCode($knetResponse['Currency']) : null,
            'error_message' => $knetResponse['ErrorText'] ?? null,
        ];
    }

    protected function mapCurrencyCode(string $code): string
    {
        return match ($code) {
            '414' => 'KWD',
            '840' => 'USD',
            '682' => 'SAR',
            '784' => 'AED',
            default => 'KWD',
        };
    }
}
