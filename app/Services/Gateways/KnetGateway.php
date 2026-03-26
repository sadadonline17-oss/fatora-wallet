<?php

namespace App\Services\Gateways;

use App\Models\Topup;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class KnetGateway implements PaymentGatewayInterface
{
    private string $merchantId;
    private string $username;
    private string $password;
    private string $resourceKey;
    private string $actionUrl;
    private string $transPortal;
    private bool $isTest;

    public function __construct()
    {
        $this->merchantId = config('fatora.knet.merchant_id');
        $this->username = config('fatora.knet.username');
        $this->password = config('fatora.knet.password');
        $this->resourceKey = config('fatora.knet.resource_key');
        $this->actionUrl = config('fatora.knet.action_url', 'https://kpay.com.kw/payment');
        $this->transPortal = config('fatora.knet.trans_portal', 'https://portal.knetpay.com.kw');
        $this->isTest = config('fatora.knet.is_test', true);
    }

    public function createCheckout(Topup $topup): array
    {
        $trackId = 'TRK' . time() . Str::random(6);
        $amount = number_format($topup->amount, 3, '.', '');
        $currency = $this->getCurrencyCode($topup->currency);
        $callbackUrl = route('api.webhooks.knet');
        $errorUrl = route('api.webhooks.knet.error');

        $topup->update([
            'track_id' => $trackId,
        ]);

        if ($this->isTest) {
            return $this->createMockCheckout($topup, $trackId, $amount);
        }

        $resource = $this->buildResource($topup, $trackId, $amount, $currency, $callbackUrl);

        try {
            $response = Http::withBasicAuth($this->username, $this->password)
                ->asForm()
                ->post($this->actionUrl, $resource);

            if ($response->successful()) {
                return [
                    'checkout_url' => $response->json('payment_url') ?? $this->transPortal,
                    'payment_id' => $trackId,
                    'request' => $resource,
                ];
            }

            Log::error('KNET checkout failed', [
                'topup_id' => $topup->id,
                'response' => $response->body(),
            ]);

            return [
                'checkout_url' => null,
                'payment_id' => $trackId,
                'request' => $resource,
                'error' => 'Failed to create KNET checkout',
            ];
        } catch (\Exception $e) {
            Log::error('KNET checkout exception', [
                'topup_id' => $topup->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'checkout_url' => null,
                'payment_id' => $trackId,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function verifyCallback(array $data): bool
    {
        if (!isset($data['signature']) || !isset($data['track_id'])) {
            return false;
        }

        $signatureData = implode('|', [
            $data['track_id'] ?? '',
            $data['auth_code'] ?? '',
            $data['amount'] ?? '',
            $data['result_code'] ?? '',
        ]);

        $expectedSignature = base64_encode(
            hash_hmac('sha256', $signatureData, $this->resourceKey, true)
        );

        return hash_equals($expectedSignature, $data['signature']);
    }

    public function parseCallback(array $data): array
    {
        $resultCode = $data['result_code'] ?? $data['ResultCode'] ?? '9999';
        $success = in_array($resultCode, ['00', '0']);

        return [
            'success' => $success,
            'track_id' => $data['track_id'] ?? $data['TrackId'] ?? null,
            'payment_id' => $data['payment_id'] ?? $data['PaymentId'] ?? null,
            'auth_code' => $data['auth_code'] ?? $data['AuthCode'] ?? null,
            'result_code' => $resultCode,
            'result_message' => $this->getResultMessage($resultCode),
            'reference' => $data['reference'] ?? $data['ReferenceId'] ?? null,
        ];
    }

    public function getCheckoutUrl(Topup $topup): string
    {
        return $this->transPortal . '/?trk=' . $topup->track_id;
    }

    public function refund(string $transactionRef, float $amount): array
    {
        $refundId = 'REF' . time() . Str::random(4);

        if ($this->isTest) {
            return [
                'success' => true,
                'refund_id' => $refundId,
                'message' => 'Mock refund successful',
            ];
        }

        try {
            $response = Http::withBasicAuth($this->username, $this->password)
                ->post($this->actionUrl . '/refund', [
                    'track_id' => $transactionRef,
                    'refund_id' => $refundId,
                    'amount' => number_format($amount, 3, '.', ''),
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

    private function buildResource(Topup $topup, string $trackId, string $amount, string $currency, string $callbackUrl): array
    {
        $resourceData = implode('|', [
            $this->merchantId,
            $trackId,
            $amount,
            $currency,
            $callbackUrl,
            $this->resourceKey,
        ]);

        return [
            'merchant_id' => $this->merchantId,
            'track_id' => $trackId,
            'amount' => $amount,
            'currency' => $currency,
            'callback_url' => $callbackUrl,
            'error_url' => route('api.webhooks.knet.error'),
            'resource' => base64_encode($resourceData),
        ];
    }

    private function createMockCheckout(Topup $topup, string $trackId, string $amount): array
    {
        $mockCheckoutUrl = route('api.webhooks.knet.mock', [
            'topup_id' => $topup->id,
            'track_id' => $trackId,
            'amount' => $amount,
        ]);

        return [
            'checkout_url' => $mockCheckoutUrl,
            'payment_id' => $trackId,
            'request' => [
                'merchant_id' => 'MOCK_' . $this->merchantId,
                'track_id' => $trackId,
                'amount' => $amount,
                'currency' => '414',
                'mode' => 'TEST',
            ],
        ];
    }

    private function getCurrencyCode(string $currency): string
    {
        return match ($currency) {
            'KWD' => '414',
            'USD' => '840',
            'SAR' => '682',
            default => '414',
        };
    }

    private function getResultMessage(string $code): string
    {
        $messages = [
            '00' => 'Transaction Approved',
            '0' => 'Transaction Approved',
            '01' => 'Refer to Card Issuer',
            '05' => 'Do Not Honor',
            '14' => 'Invalid Card Number',
            '51' => 'Insufficient Balance',
            '54' => 'Expired Card',
            '57' => 'Transaction Not Permitted',
            '99' => 'Generic Error',
            '9999' => 'Unknown Error',
        ];

        return $messages[$code] ?? "Error Code: {$code}";
    }
}
