<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTopup;
use Illuminate\Foundation\Testing\RefreshDatabase;

class WalletTopupTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Wallet $wallet;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create([
            'email' => 'test@example.com',
        ]);
        
        $this->wallet = $this->user->createWallet('KWD');
    }

    public function test_user_can_get_wallets(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/wallet');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'wallets' => [
                        '*' => ['id', 'currency', 'balance', 'account_number']
                    ]
                ]
            ]);
    }

    public function test_user_can_topup_wallet(): void
    {
        config(['payment.gateways.knet.test_mode' => true]);
        
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/wallet/topup', [
                'amount' => 100,
                'currency' => 'KWD',
                'gateway' => 'knet',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'transaction_id',
                    'payment_url',
                    'amount',
                ]
            ]);

        $this->assertDatabaseHas('wallet_topups', [
            'wallet_id' => $this->wallet->id,
            'amount' => 100,
            'gateway' => 'knet',
            'status' => 'pending',
        ]);
    }

    public function test_topup_validates_amount(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/wallet/topup', [
                'amount' => 0.5,
                'currency' => 'KWD',
                'gateway' => 'knet',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_topup_validates_gateway(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/wallet/topup', [
                'amount' => 100,
                'currency' => 'KWD',
                'gateway' => 'invalid_gateway',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['gateway']);
    }

    public function test_user_can_check_topup_status(): void
    {
        $topup = WalletTopup::create([
            'wallet_id' => $this->wallet->id,
            'amount' => 50,
            'currency' => 'KWD',
            'gateway' => 'knet',
            'transaction_id' => 'TXN-TEST-123',
            'status' => 'pending',
            'net_amount' => 50,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/wallet/topup/{$topup->transaction_id}/status");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'transaction_id' => 'TXN-TEST-123',
                    'amount' => 50,
                    'status' => 'pending',
                ]
            ]);
    }

    public function test_knet_callback_confirms_payment(): void
    {
        $topup = WalletTopup::create([
            'wallet_id' => $this->wallet->id,
            'amount' => 100,
            'currency' => 'KWD',
            'gateway' => 'knet',
            'transaction_id' => 'TXN-CALLBACK-123',
            'status' => 'pending',
            'net_amount' => 100,
        ]);

        $callbackData = [
            'Result' => 'CAPTURED',
            'TrackId' => 'TXN-CALLBACK-123',
            'AuthCode' => '123456',
            'Ref' => 'KNET-REF-123',
            'PostDate' => date('d/m/Y'),
            'Amt' => 100,
        ];

        $response = $this->postJson('/api/v1/wallet/webhook/knet', $callbackData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'result' => 'CAPTURED',
            ]);

        $topup->refresh();
        $this->assertEquals('completed', $topup->status);
        $this->assertEquals(100, (float) $this->wallet->fresh()->balance);
    }

    public function test_knet_callback_fails_for_invalid_payment(): void
    {
        $topup = WalletTopup::create([
            'wallet_id' => $this->wallet->id,
            'amount' => 100,
            'currency' => 'KWD',
            'gateway' => 'knet',
            'transaction_id' => 'TXN-FAILED-123',
            'status' => 'pending',
            'net_amount' => 100,
        ]);

        $callbackData = [
            'Result' => 'DECLINED',
            'TrackId' => 'TXN-FAILED-123',
        ];

        $response = $this->postJson('/api/v1/wallet/webhook/knet', $callbackData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_user_can_get_transactions(): void
    {
        $this->wallet->credit(100, 'Test credit');

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/wallet/transactions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'transactions',
                    'pagination',
                ]
            ]);
    }
}
