<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class WalletFactory extends Factory
{
    protected $model = Wallet::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'uuid' => Str::uuid()->toString(),
            'label' => 'Main Wallet',
            'currency' => 'KWD',
            'balance' => $this->faker->randomFloat(3, 0, 10000),
            'frozen_balance' => 0,
            'pending_balance' => 0,
            'daily_limit' => 5000.000,
            'monthly_limit' => 25000.000,
            'daily_spent' => 0,
            'monthly_spent' => 0,
            'status' => 'active',
        ];
    }

    public function empty(): static
    {
        return $this->state(fn (array $attributes) => [
            'balance' => 0,
        ]);
    }

    public function frozen(): static
    {
        return $this->state(fn (array $attributes) => [
            'frozen_balance' => 100.000,
        ]);
    }

    public function currency(string $currency): static
    {
        return $this->state(fn (array $attributes) => [
            'currency' => $currency,
        ]);
    }
}
