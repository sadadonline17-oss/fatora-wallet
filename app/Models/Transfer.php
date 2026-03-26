<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Transfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'sender_wallet_id',
        'receiver_wallet_id',
        'sender_transaction_id',
        'receiver_transaction_id',
        'amount',
        'currency',
        'fee',
        'status',
        'qr_code',
        'pin_code',
        'expires_at',
        'claimed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:3',
            'fee' => 'decimal:3',
            'expires_at' => 'datetime',
            'claimed_at' => 'datetime',
        ];
    }

    public const STATUS_PENDING = 'pending';
    public const STATUS_CLAIMED = 'claimed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($transfer) {
            if (empty($transfer->uuid)) {
                $transfer->uuid = Str::uuid()->toString();
            }
            if (empty($transfer->pin_code)) {
                $transfer->pin_code = Str::random(6);
            }
            if (empty($transfer->qr_code)) {
                $transfer->qr_code = config('app.url') . '/transfer/' . $transfer->uuid;
            }
            if (empty($transfer->expires_at)) {
                $transfer->expires_at = now()->addDays(7);
            }
        });
    }

    public function senderWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'sender_wallet_id');
    }

    public function receiverWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'receiver_wallet_id');
    }

    public function senderTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'sender_transaction_id');
    }

    public function receiverTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'receiver_transaction_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isClaimed(): bool
    {
        return $this->status === self::STATUS_CLAIMED;
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED;
    }

    public function markAsClaimed(): void
    {
        $this->status = self::STATUS_CLAIMED;
        $this->claimed_at = now();
        $this->save();
    }

    public function markAsCancelled(): void
    {
        $this->status = self::STATUS_CANCELLED;
        $this->save();
    }

    public function markAsExpired(): void
    {
        $this->status = self::STATUS_EXPIRED;
        $this->save();
    }

    public function getFormattedAmount(): string
    {
        return number_format($this->amount, 3) . ' ' . $this->currency;
    }
}
