<?php

namespace App\Services;

interface PaymentGatewayInterface
{
    public function initialize(array $config): void;
    
    public function createPayment(float $amount, string $currency, array $metadata = []): PaymentResponse;
    
    public function verifyPayment(string $transactionId): VerificationResponse;
    
    public function refund(string $transactionId, float $amount): RefundResponse;
    
    public function getPaymentUrl(string $transactionId): string;
}
