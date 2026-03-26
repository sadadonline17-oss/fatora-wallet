<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'wallet_id',
        'user_id',
        'reference_id',
        'type',
        'operation',
        'amount',
        'currency',
        'fee',
        'net_amount',
        'status',
        'provider',
        'provider_ref',
        'provider_track_id',
        'metadata',
        'original_response',
        'description',
        'qr_code',
        'expires_at',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:3',
            'fee' => 'decimal:3',
            'net_amount' => 'decimal:3',
            'metadata' => 'array',
            'original_response' => 'array',
            'expires_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public const TYPE_TOPUP = 'topup';
    public const TYPE_WITHDRAWAL = 'withdrawal';
    public const TYPE_TRANSFER = 'transfer';
    public const TYPE_PAYMENT = 'payment';
    public const TYPE_REFUND = 'refund';
    public const TYPE_FEE = 'fee';
    public const TYPE_FROZEN = 'frozen';
    public const TYPE_UNFROZEN = 'unfrozen';

    public const OPERATION_CREDIT = 'credit';
    public const OPERATION_DEBIT = 'debit';

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isCredit(): bool
    {
        return $this->operation === self::OPERATION_CREDIT;
    }

    public function isDebit(): bool
    {
        return $this->operation === self::OPERATION_DEBIT;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return in_array($this->status, [self::STATUS_FAILED, self::STATUS_CANCELLED, self::STATUS_EXPIRED]);
    }

    public function markAsCompleted(): void
    {
        $this->status = self::STATUS_COMPLETED;
        $this->processed_at = now();
        $this->save();
    }

    public function markAsFailed(string $reason = null): void
    {
        $this->status = self::STATUS_FAILED;
        $this->metadata = array_merge($this->metadata ?? [], ['failure_reason' => $reason]);
        $this->save();
    }

    public function markAsProcessing(): void
    {
        $this->status = self::STATUS_PROCESSING;
        $this->save();
    }

    public function markAsExpired(): void
    {
        $this->status = self::STATUS_EXPIRED;
        $this->save();
    }

    public function markAsCancelled(): void
    {
        $this->status = self::STATUS_CANCELLED;
        $this->save();
    }
}
