<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTopup extends Model
{
    use HasFactory;

    protected $fillable = [
        'wallet_id',
        'amount',
        'currency',
        'gateway',
        'gateway_transaction_id',
        'transaction_id',
        'payment_url',
        'status',
        'fees',
        'net_amount',
        'metadata',
        'paid_at',
        'cancelled_at',
    ];

    protected $casts = [
        'amount' => 'decimal:3',
        'fees' => 'decimal:3',
        'net_amount' => 'decimal:3',
        'metadata' => 'array',
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_REFUNDED = 'refunded';

    const GATEWAY_KNET = 'knet';
    const GATEWAY_PAYTABS = 'paytabs';
    const GATEWAY_MYFATOORAH = 'myfatoorah';

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(WalletTransaction::class, 'transaction_id', 'id');
    }

    public function markAsProcessing(): self
    {
        $this->update(['status' => self::STATUS_PROCESSING]);
        return $this;
    }

    public function confirm(): bool
    {
        if ($this->status !== self::STATUS_PENDING && $this->status !== self::STATUS_PROCESSING) {
            return false;
        }

        \DB::beginTransaction();
        try {
            $this->update([
                'status' => self::STATUS_COMPLETED,
                'paid_at' => now(),
            ]);

            $wallet = $this->wallet;
            $wallet->credit($this->net_amount, "Wallet topup via {$this->gateway}", [
                'topup_id' => $this->id,
                'gateway' => $this->gateway,
                'gateway_transaction_id' => $this->gateway_transaction_id,
            ]);

            \DB::commit();
            return true;
        } catch (\Exception $e) {
            \DB::rollBack();
            throw $e;
        }
    }

    public function cancel(): bool
    {
        if ($this->status === self::STATUS_COMPLETED || $this->status === self::STATUS_REFUNDED) {
            return false;
        }

        $this->update([
            'status' => self::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);

        return true;
    }

    public function fail(string $reason = ''): self
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'metadata' => array_merge($this->metadata ?? [], ['failure_reason' => $reason]),
        ]);
        return $this;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function calculateFees(): float
    {
        $feeConfig = config("payment.gateways.{$this->gateway}.fees", [
            'type' => 'percentage',
            'value' => 2.0,
            'min' => 0.100,
            'max' => 10.000,
        ]);

        $fees = 0;
        if ($feeConfig['type'] === 'percentage') {
            $fees = ($this->amount * $feeConfig['value']) / 100;
        } else {
            $fees = $feeConfig['value'];
        }

        $fees = max($fees, $feeConfig['min'] ?? 0);
        $fees = min($fees, $feeConfig['max'] ?? PHP_FLOAT_MAX);

        return round($fees, 3);
    }

    public static function generateTransactionId(): string
    {
        return 'TXN-' . date('Ymd') . '-' . strtoupper(uniqid());
    }
}
