<?php

namespace App\Services\Gateways;

use App\Models\Topup;

interface PaymentGatewayInterface
{
    public function createCheckout(Topup $topup): array;

    public function verifyCallback(array $data): bool;

    public function parseCallback(array $data): array;

    public function getCheckoutUrl(Topup $topup): string;

    public function refund(string $transactionRef, float $amount): array;
}
