<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'type' => $this->type,
            'operation' => $this->operation,
            'amount' => (float) $this->amount,
            'currency' => $this->currency,
            'fee' => (float) $this->fee,
            'net_amount' => (float) $this->net_amount,
            'status' => $this->status,
            'provider' => $this->provider,
            'provider_ref' => $this->provider_ref,
            'description' => $this->description,
            'qr_code' => $this->qr_code,
            'processed_at' => $this->processed_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
