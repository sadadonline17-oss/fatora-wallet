<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@fatora.kw',
            'phone' => '+96599999999',
            'password' => Hash::make('password'),
            'type' => 'user',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        Wallet::create([
            'user_id' => $user->id,
            'label' => 'Main Wallet',
            'currency' => 'KWD',
            'balance' => 100.000,
            'daily_limit' => 5000.000,
            'monthly_limit' => 25000.000,
            'status' => 'active',
        ]);

        $merchant = User::create([
            'name' => 'Test Merchant',
            'email' => 'merchant@fatora.kw',
            'phone' => '+96598888888',
            'password' => Hash::make('password'),
            'type' => 'merchant',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        Wallet::create([
            'user_id' => $merchant->id,
            'label' => 'Business Account',
            'currency' => 'KWD',
            'balance' => 5000.000,
            'daily_limit' => 50000.000,
            'monthly_limit' => 200000.000,
            'status' => 'active',
        ]);

        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@fatora.kw',
            'phone' => '+96597777777',
            'password' => Hash::make('admin123'),
            'type' => 'admin',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }
}
