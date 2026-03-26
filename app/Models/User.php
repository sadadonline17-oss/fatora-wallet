<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'country_code',
        'status',
        'email_verified_at',
        'phone_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    const STATUS_ACTIVE = 'active';
    const STATUS_SUSPENDED = 'suspended';
    const STATUS_INACTIVE = 'inactive';

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    public function wallets(): HasMany
    {
        return $this->hasMany(Wallet::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasManyThrough(
            WalletTransaction::class,
            Wallet::class,
            'user_id',
            'wallet_id'
        );
    }

    public function hasWallet(string $currency = 'KWD'): bool
    {
        return $this->wallets()
            ->where('currency', $currency)
            ->where('status', Wallet::STATUS_ACTIVE)
            ->exists();
    }

    public function getWallet(string $currency = 'KWD'): ?Wallet
    {
        return $this->wallets()
            ->where('currency', $currency)
            ->where('status', Wallet::STATUS_ACTIVE)
            ->first();
    }

    public function getOrCreateWallet(string $currency = 'KWD'): Wallet
    {
        return $this->getWallet($currency) ?? $this->createWallet($currency);
    }

    public function createWallet(string $currency = 'KWD', float $initialBalance = 0): Wallet
    {
        return $this->wallets()->create([
            'currency' => $currency,
            'balance' => $initialBalance,
            'pending_balance' => 0,
            'available_balance' => $initialBalance,
            'status' => Wallet::STATUS_ACTIVE,
            'account_number' => $this->generateAccountNumber($currency),
        ]);
    }

    protected function generateAccountNumber(string $currency): string
    {
        $prefix = match ($currency) {
            'KWD' => '968',
            'SAR' => '966',
            'AED' => '971',
            'USD' => '001',
            default => '968',
        };
        return $prefix . str_pad($this->id, 10, '0', STR_PAD_LEFT);
    }
}
