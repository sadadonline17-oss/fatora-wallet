<?php

namespace Tests\Unit;

use App\Models\Wallet;
use App\Models\User;
use PHPUnit\Framework\TestCase;

class WalletTest extends TestCase
{
    public function test_wallet_can_calculate_available_balance(): void
    {
        $wallet = new Wallet();
        $wallet->balance = 100.000;
        $wallet->frozen_balance = 25.000;

        $this->assertEquals(75.000, $wallet->availableBalance());
    }

    public function test_wallet_can_add_balance(): void
    {
        $wallet = new Wallet();
        $wallet->balance = 100.000;

        $wallet->addBalance(50.000);

        $this->assertEquals(150.000, $wallet->balance);
    }

    public function test_wallet_can_subtract_balance(): void
    {
        $wallet = new Wallet();
        $wallet->balance = 100.000;

        $wallet->subtractBalance(30.000);

        $this->assertEquals(70.000, $wallet->balance);
    }

    public function test_wallet_can_freeze_balance(): void
    {
        $wallet = new Wallet();
        $wallet->frozen_balance = 0.000;

        $wallet->freezeBalance(50.000);

        $this->assertEquals(50.000, $wallet->frozen_balance);
    }

    public function test_wallet_can_unfreeze_balance(): void
    {
        $wallet = new Wallet();
        $wallet->frozen_balance = 50.000;

        $wallet->unfreezeBalance(25.000);

        $this->assertEquals(25.000, $wallet->frozen_balance);
    }

    public function test_wallet_check_withdraw_with_insufficient_balance(): void
    {
        $wallet = new Wallet();
        $wallet->balance = 50.000;
        $wallet->frozen_balance = 0.000;
        $wallet->status = 'active';

        $this->assertFalse($wallet->canWithdraw(100.000));
    }

    public function test_wallet_check_withdraw_with_frozen_balance(): void
    {
        $wallet = new Wallet();
        $wallet->balance = 100.000;
        $wallet->frozen_balance = 75.000;
        $wallet->status = 'active';

        $this->assertFalse($wallet->canWithdraw(30.000));
    }

    public function test_wallet_check_withdraw_exceeds_daily_limit(): void
    {
        $wallet = new Wallet();
        $wallet->balance = 1000.000;
        $wallet->frozen_balance = 0.000;
        $wallet->daily_limit = 500.000;
        $wallet->daily_spent = 400.000;
        $wallet->status = 'active';

        $this->assertFalse($wallet->canWithdraw(200.000));
    }

    public function test_wallet_is_active(): void
    {
        $wallet = new Wallet();
        $wallet->status = 'active';

        $this->assertTrue($wallet->isActive());
    }

    public function test_wallet_is_inactive(): void
    {
        $wallet = new Wallet();
        $wallet->status = 'frozen';

        $this->assertFalse($wallet->isActive());
    }
}
