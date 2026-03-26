<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TopupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'provider' => $this->provider,
            'amount' => (float) $this->amount,
            'currency' => $this->currency,
            'fee' => (float) $this->fee,
            'net_amount' => (float) $this->net_amount,
            'status' => $this->status,
            'checkout_url' => $this->checkout_url,
            'track_id' => $this->track_id,
            'payment_id' => $this->payment_id,
            'auth_code' => $this->auth_code,
            'result_code' => $this->result_code,
            'result_message' => $this->result_message,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
