<?php

namespace App\Services;

class RefundResponse
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $refundId = null,
        public readonly ?float $amount = null,
        public readonly ?string $status = null,
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
            'refund_id' => $this->refundId,
            'amount' => $this->amount,
            'status' => $this->status,
            'error_code' => $this->errorCode,
            'error_message' => $this->errorMessage,
        ];
    }
}
