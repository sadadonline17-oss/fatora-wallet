<?php

namespace App\Services;

class PaymentResponse
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $transactionId = null,
        public readonly ?string $paymentUrl = null,
        public readonly ?string $errorCode = null,
        public readonly ?string $errorMessage = null,
        public readonly array $rawResponse = [],
    ) {}

    public function isSuccessful(): bool
    {
        return $this->success;
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'transaction_id' => $this->transactionId,
            'payment_url' => $this->paymentUrl,
            'error_code' => $this->errorCode,
            'error_message' => $this->errorMessage,
        ];
    }
}
