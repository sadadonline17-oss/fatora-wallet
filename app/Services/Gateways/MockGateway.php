<?php

namespace App\Services\Gateways;

use App\Models\Topup;
use Illuminate\Support\Str;

class MockGateway implements PaymentGatewayInterface
{
    public function createCheckout(Topup $topup): array
    {
        $mockId = 'MOCK_' . time() . '_' . Str::random(8);

        return [
            'checkout_url' => route('api.webhooks.mock.complete', [
                'topup_id' => $topup->id,
                'mock_id' => $mockId,
            ]),
            'payment_id' => $mockId,
            'request' => [
                'amount' => $topup->amount,
                'currency' => $topup->currency,
                'mode' => 'MOCK',
            ],
            'instructions' => 'Click the checkout URL to simulate successful payment',
        ];
    }

    public function verifyCallback(array $data): bool
    {
        return true;
    }

    public function parseCallback(array $data): array
    {
        return [
            'success' => true,
            'mock_id' => $data['mock_id'] ?? null,
            'auth_code' => 'MOCK_AUTH',
            'track_id' => $data['mock_id'] ?? null,
            'result_code' => '00',
            'result_message' => 'Mock Transaction Approved',
        ];
    }

    public function getCheckoutUrl(Topup $topup): string
    {
        return route('api.webhooks.mock.complete', [
            'topup_id' => $topup->id,
            'mock_id' => 'MOCK_' . Str::random(12),
        ]);
    }

    public function refund(string $transactionRef, float $amount): array
    {
        return [
            'success' => true,
            'refund_id' => 'MOCK_REFUND_' . Str::random(8),
            'message' => 'Mock refund successful',
        ];
    }
}
