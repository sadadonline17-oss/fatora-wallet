<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'label' => $this->label,
            'currency' => $this->currency,
            'balance' => (float) $this->balance,
            'frozen_balance' => (float) $this->frozen_balance,
            'available_balance' => $this->availableBalance(),
            'status' => $this->status,
            'daily_limit' => $this->daily_limit ? (float) $this->daily_limit : null,
            'daily_spent' => (float) $this->daily_spent,
            'monthly_limit' => $this->monthly_limit ? (float) $this->monthly_limit : null,
            'monthly_spent' => (float) $this->monthly_spent,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
