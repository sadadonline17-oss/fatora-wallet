<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Topup extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'user_id',
        'wallet_id',
        'provider',
        'amount',
        'currency',
        'fee',
        'net_amount',
        'status',
        'checkout_url',
        'payment_id',
        'track_id',
        'auth_code',
        'result_code',
        'result_message',
        'request_payload',
        'response_payload',
        'paid_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:3',
            'fee' => 'decimal:3',
            'net_amount' => 'decimal:3',
            'request_payload' => 'array',
            'response_payload' => 'array',
            'paid_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public const PROVIDER_KNET = 'knet';
    public const PROVIDER_EMV = 'emv';
    public const PROVIDER_CARD = 'card';
    public const PROVIDER_CRYPTO = 'crypto';

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($topup) {
            if (empty($topup->uuid)) {
                $topup->uuid = Str::uuid()->toString();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
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

    public function markAsCompleted(string $authCode = null, string $trackId = null): void
    {
        $this->status = self::STATUS_COMPLETED;
        $this->paid_at = now();
        if ($authCode) {
            $this->auth_code = $authCode;
        }
        if ($trackId) {
            $this->track_id = $trackId;
        }
        $this->save();
    }

    public function markAsFailed(string $resultCode = null, string $resultMessage = null): void
    {
        $this->status = self::STATUS_FAILED;
        if ($resultCode) {
            $this->result_code = $resultCode;
        }
        if ($resultMessage) {
            $this->result_message = $resultMessage;
        }
        $this->save();
    }

    public function markAsProcessing(): void
    {
        $this->status = self::STATUS_PROCESSING;
        $this->save();
    }

    public function markAsCancelled(): void
    {
        $this->status = self::STATUS_CANCELLED;
        $this->save();
    }

    public function getFormattedAmount(): string
    {
        return number_format($this->amount, 3) . ' ' . $this->currency;
    }
}
