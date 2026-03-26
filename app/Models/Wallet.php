<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Wallet extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'currency',
        'balance',
        'pending_balance',
        'available_balance',
        'status',
        'account_number',
        'pin_hash',
        'last_transaction_at',
        'metadata',
    ];

    protected $casts = [
        'balance' => 'decimal:3',
        'pending_balance' => 'decimal:3',
        'available_balance' => 'decimal:3',
        'metadata' => 'array',
        'last_transaction_at' => 'datetime',
    ];

    const STATUS_ACTIVE = 'active';
    const STATUS_SUSPENDED = 'suspended';
    const STATUS_CLOSED = 'closed';
    const STATUS_PENDING_VERIFICATION = 'pending_verification';

    const CURRENCY_KWD = 'KWD';
    const CURRENCY_SAR = 'SAR';
    const CURRENCY_AED = 'AED';
    const CURRENCY_USD = 'USD';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function topups()
    {
        return $this->hasMany(WalletTopup::class);
    }

    public function transactions()
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function credit(float $amount, string $description, array $metadata = []): WalletTransaction
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be positive');
        }

        $this->lockForUpdate();

        $this->balance = bcadd($this->balance, $amount, 3);
        $this->available_balance = bcadd($this->available_balance, $amount, 3);
        $this->last_transaction_at = now();
        $this->save();

        return $this->transactions()->create([
            'type' => WalletTransaction::TYPE_CREDIT,
            'amount' => $amount,
            'balance_before' => bcsub($this->balance, $amount, 3),
            'balance_after' => $this->balance,
            'description' => $description,
            'reference_id' => $metadata['reference_id'] ?? null,
            'metadata' => $metadata,
            'status' => WalletTransaction::STATUS_COMPLETED,
        ]);
    }

    public function debit(float $amount, string $description, array $metadata = []): WalletTransaction
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be positive');
        }

        if (bccomp($this->available_balance, $amount, 3) < 0) {
            throw new \RuntimeException('Insufficient balance');
        }

        $this->lockForUpdate();

        $this->balance = bcsub($this->balance, $amount, 3);
        $this->available_balance = bcsub($this->available_balance, $amount, 3);
        $this->last_transaction_at = now();
        $this->save();

        return $this->transactions()->create([
            'type' => WalletTransaction::TYPE_DEBIT,
            'amount' => $amount,
            'balance_before' => bcadd($this->balance, $amount, 3),
            'balance_after' => $this->balance,
            'description' => $description,
            'reference_id' => $metadata['reference_id'] ?? null,
            'metadata' => $metadata,
            'status' => WalletTransaction::STATUS_COMPLETED,
        ]);
    }

    public function reserve(float $amount): void
    {
        if (bccomp($this->available_balance, $amount, 3) < 0) {
            throw new \RuntimeException('Insufficient balance');
        }

        $this->lockForUpdate();
        $this->available_balance = bcsub($this->available_balance, $amount, 3);
        $this->pending_balance = bcadd($this->pending_balance, $amount, 3);
        $this->save();
    }

    public function release(float $amount): void
    {
        $this->lockForUpdate();
        $this->pending_balance = bcsub($this->pending_balance, $amount, 3);
        $this->available_balance = bcadd($this->available_balance, $amount, 3);
        $this->save();
    }

    public function confirmPayment(string $transactionId): bool
    {
        $topup = WalletTopup::where('transaction_id', $transactionId)
            ->where('status', WalletTopup::STATUS_PENDING)
            ->first();

        if (!$topup) {
            return false;
        }

        return $topup->confirm();
    }

    public function cancelPayment(string $transactionId): bool
    {
        $topup = WalletTopup::where('transaction_id', $transactionId)
            ->whereIn('status', [WalletTopup::STATUS_PENDING, WalletTopup::STATUS_PROCESSING])
            ->first();

        if (!$topup) {
            return false;
        }

        return $topup->cancel();
    }
}
