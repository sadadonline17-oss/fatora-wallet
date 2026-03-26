<?php

namespace App\Services\Gateways;

use App\Models\Topup;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EmvGateway implements PaymentGatewayInterface
{
    private string $terminalId;
    private string $terminalKey;
    private bool $isDemo;

    public function __construct()
    {
        $this->terminalId = config('fatora.emv.terminal_id', 'EMV001');
        $this->terminalKey = config('fatora.emv.terminal_key', 'demo_key');
        $this->isDemo = config('fatora.emv.is_demo', true);
    }

    public function createCheckout(Topup $topup): array
    {
        $transactionId = 'EMV' . time() . Str::random(4);

        $topup->update([
            'payment_id' => $transactionId,
        ]);

        if ($this->isDemo) {
            return $this->createDemoCheckout($topup, $transactionId);
        }

        $posData = $this->buildPosData($topup, $transactionId);

        return [
            'checkout_url' => null,
            'payment_id' => $transactionId,
            'pos_reference' => $posData['reference'],
            'request' => $posData,
        ];
    }

    public function verifyCallback(array $data): bool
    {
        if (!isset($data['signature']) || !isset($data['transaction_id'])) {
            return false;
        }

        $signatureData = implode('|', [
            $data['transaction_id'] ?? '',
            $data['amount'] ?? '',
            $data['auth_code'] ?? '',
        ]);

        $expectedSignature = hash_hmac('sha256', $signatureData, $this->terminalKey);

        return hash_equals($expectedSignature, $data['signature']);
    }

    public function parseCallback(array $data): array
    {
        $resultCode = $data['response_code'] ?? $data['responseCode'] ?? '99';
        $success = in_array($resultCode, ['00', '0', 'approved']);

        return [
            'success' => $success,
            'transaction_id' => $data['transaction_id'] ?? $data['transactionId'] ?? null,
            'auth_code' => $data['auth_code'] ?? $data['authCode'] ?? null,
            'card_last4' => $data['card_last4'] ?? $data['masked_pan'] ?? null,
            'result_code' => $resultCode,
            'result_message' => $this->getResultMessage($resultCode),
            'terminal_id' => $data['terminal_id'] ?? null,
        ];
    }

    public function getCheckoutUrl(Topup $topup): string
    {
        return "emv://pay?tid={$this->terminalId}&ref={$topup->payment_id}";
    }

    public function refund(string $transactionRef, float $amount): array
    {
        $refundId = 'REME' . time() . Str::random(4);

        if ($this->isDemo) {
            return [
                'success' => true,
                'refund_id' => $refundId,
                'message' => 'EMV Demo refund successful',
            ];
        }

        try {
            Log::info('EMV Refund request', [
                'original_ref' => $transactionRef,
                'refund_id' => $refundId,
                'amount' => $amount,
            ]);

            return [
                'success' => true,
                'refund_id' => $refundId,
                'message' => 'Refund processed',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'refund_id' => $refundId,
                'message' => $e->getMessage(),
            ];
        }
    }

    private function buildPosData(Topup $topup, string $transactionId): array
    {
        $amount = number_format($topup->amount, 3, '.', '');
        $timestamp = now()->format('YmdHis');

        $signatureData = implode('|', [
            $this->terminalId,
            $transactionId,
            $amount,
            $topup->currency,
            $timestamp,
        ]);

        return [
            'terminal_id' => $this->terminalId,
            'transaction_id' => $transactionId,
            'amount' => $amount,
            'currency' => $topup->currency,
            'timestamp' => $timestamp,
            'merchant_name' => 'Fatora Wallet',
            'signature' => hash_hmac('sha256', $signatureData, $this->terminalKey),
            'callback_url' => route('api.webhooks.emv'),
        ];
    }

    private function createDemoCheckout(Topup $topup, string $transactionId): array
    {
        return [
            'checkout_url' => route('api.webhooks.emv.mock', [
                'topup_id' => $topup->id,
                'transaction_id' => $transactionId,
            ]),
            'payment_id' => $transactionId,
            'pos_reference' => "DEMO_{$transactionId}",
            'request' => [
                'terminal_id' => 'DEMO_' . $this->terminalId,
                'transaction_id' => $transactionId,
                'amount' => number_format($topup->amount, 3, '.', ''),
                'currency' => $topup->currency,
                'mode' => 'DEMO',
            ],
            'demo_notice' => 'This is a demo EMV checkout. Use the mock endpoint to simulate payment.',
        ];
    }

    private function getResultMessage(string $code): string
    {
        $messages = [
            '00' => 'Approved',
            '0' => 'Approved',
            'approved' => 'Approved',
            '01' => 'Refer to Issuer',
            '05' => 'Declined',
            '14' => 'Invalid Card',
            '51' => 'Insufficient Funds',
            '54' => 'Expired Card',
            '57' => 'Not Permitted',
            '99' => 'Communication Error',
        ];

        return $messages[$code] ?? "Response: {$code}";
    }
}
