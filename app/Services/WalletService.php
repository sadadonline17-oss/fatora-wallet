<?php

namespace App\Services;

use App\Models\Topup;
use App\Models\Transaction;
use App\Models\Transfer;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WalletService
{
    public function __construct(
        private PaymentGatewayFactory $gatewayFactory
    ) {}

    public function createWallet(int $userId, string $currency = 'KWD', string $label = 'Main Wallet'): Wallet
    {
        return DB::transaction(function () use ($userId, $currency, $label) {
            return Wallet::create([
                'user_id' => $userId,
                'label' => $label,
                'currency' => $currency,
                'balance' => 0,
                'status' => 'active',
            ]);
        });
    }

    public function createTopup(Wallet $wallet, float $amount, string $provider): Topup
    {
        $fee = $this->calculateFee($amount, $provider);
        $netAmount = bcsub((string) $amount, (string) $fee, 3);

        $topup = DB::transaction(function () use ($wallet, $amount, $provider, $fee, $netAmount) {
            $topup = Topup::create([
                'user_id' => $wallet->user_id,
                'wallet_id' => $wallet->id,
                'provider' => $provider,
                'amount' => $amount,
                'currency' => $wallet->currency,
                'fee' => $fee,
                'net_amount' => $netAmount,
                'status' => Topup::STATUS_PENDING,
                'expires_at' => now()->addHours(24),
            ]);

            $this->createTransaction($wallet, $topup, Transaction::TYPE_TOPUP, $amount, Transaction::OPERATION_CREDIT);

            return $topup;
        });

        $gateway = $this->gatewayFactory->make($provider);
        $checkoutData = $gateway->createCheckout($topup);

        $topup->update([
            'checkout_url' => $checkoutData['checkout_url'] ?? null,
            'payment_id' => $checkoutData['payment_id'] ?? null,
            'request_payload' => $checkoutData['request'] ?? null,
        ]);

        return $topup;
    }

    public function processTopupCallback(Topup $topup, array $response): bool
    {
        if ($topup->status !== Topup::STATUS_PENDING) {
            Log::warning("Topup {$topup->uuid} already processed", ['status' => $topup->status]);
            return false;
        }

        $gateway = $this->gatewayFactory->make($topup->provider);

        if (!$gateway->verifyCallback($response)) {
            Log::error("Invalid callback signature for topup {$topup->uuid}");
            return false;
        }

        $result = $gateway->parseCallback($response);

        return DB::transaction(function () use ($topup, $result, $response) {
            if ($result['success']) {
                $topup->markAsCompleted(
                    $result['auth_code'] ?? null,
                    $result['track_id'] ?? null
                );

                $topup->wallet->addBalance($topup->net_amount);

                $transaction = $topup->transaction;
                if ($transaction) {
                    $transaction->markAsCompleted();
                }

                Log::info("Topup {$topup->uuid} completed", [
                    'amount' => $topup->amount,
                    'currency' => $topup->currency,
                ]);

                return true;
            }

            $topup->markAsFailed(
                $result['result_code'] ?? 'UNKNOWN',
                $result['result_message'] ?? 'Payment failed'
            );

            return false;
        });
    }

    public function withdraw(Wallet $wallet, float $amount, array $metadata = []): Transaction
    {
        if (!$wallet->canWithdraw($amount)) {
            throw new \Exception('Insufficient balance or limit exceeded');
        }

        $fee = $this->calculateFee($amount, 'withdrawal');
        $netAmount = bcsub((string) $amount, (string) $fee, 3);

        return DB::transaction(function () use ($wallet, $amount, $fee, $netAmount, $metadata) {
            $wallet->subtractBalance($amount);
            $wallet->addSpending($amount);

            $transaction = Transaction::create([
                'wallet_id' => $wallet->id,
                'user_id' => $wallet->user_id,
                'type' => Transaction::TYPE_WITHDRAWAL,
                'operation' => Transaction::OPERATION_DEBIT,
                'amount' => $amount,
                'currency' => $wallet->currency,
                'fee' => $fee,
                'net_amount' => $netAmount,
                'status' => Transaction::STATUS_PROCESSING,
                'metadata' => $metadata,
                'description' => 'Withdrawal',
            ]);

            return $transaction;
        });
    }

    public function transfer(Wallet $sender, Wallet $receiver, float $amount, ?string $description = null): Transfer
    {
        if ($sender->id === $receiver->id) {
            throw new \Exception('Cannot transfer to same wallet');
        }

        if (!$sender->canWithdraw($amount)) {
            throw new \Exception('Insufficient balance or limit exceeded');
        }

        if ($sender->currency !== $receiver->currency) {
            throw new \Exception('Currency mismatch between wallets');
        }

        $fee = $this->calculateFee($amount, 'transfer');
        $netAmount = bcsub((string) $amount, (string) $fee, 3);

        return DB::transaction(function () use ($sender, $receiver, $amount, $fee, $netAmount, $description) {
            $transfer = Transfer::create([
                'sender_wallet_id' => $sender->id,
                'receiver_wallet_id' => $receiver->id,
                'amount' => $amount,
                'currency' => $sender->currency,
                'fee' => $fee,
                'status' => Transfer::STATUS_PENDING,
            ]);

            $senderTransaction = $this->createTransaction(
                $sender,
                $transfer,
                Transaction::TYPE_TRANSFER,
                $amount,
                Transaction::OPERATION_DEBIT,
                $description
            );

            $transfer->sender_transaction_id = $senderTransaction->id;
            $transfer->save();

            $receiverTransaction = $this->createTransaction(
                $receiver,
                $transfer,
                Transaction::TYPE_TRANSFER,
                $netAmount,
                Transaction::OPERATION_CREDIT,
                $description
            );

            $transfer->receiver_transaction_id = $receiverTransaction->id;
            $transfer->save();

            $sender->subtractBalance($amount);
            $sender->addSpending($amount);

            $receiver->addBalance($netAmount);

            $senderTransaction->markAsCompleted();
            $receiverTransaction->markAsCompleted();
            $transfer->markAsClaimed();

            return $transfer;
        });
    }

    public function processPayment(Wallet $wallet, float $amount, string $merchantRef, array $metadata = []): Transaction
    {
        if (!$wallet->canWithdraw($amount)) {
            throw new \Exception('Insufficient balance or limit exceeded');
        }

        $fee = $this->calculateFee($amount, 'payment');
        $netAmount = bcsub((string) $amount, (string) $fee, 3);

        return DB::transaction(function () use ($wallet, $amount, $fee, $netAmount, $merchantRef, $metadata) {
            $wallet->subtractBalance($amount);
            $wallet->addSpending($amount);

            return Transaction::create([
                'wallet_id' => $wallet->id,
                'user_id' => $wallet->user_id,
                'reference_id' => $merchantRef,
                'type' => Transaction::TYPE_PAYMENT,
                'operation' => Transaction::OPERATION_DEBIT,
                'amount' => $amount,
                'currency' => $wallet->currency,
                'fee' => $fee,
                'net_amount' => $netAmount,
                'status' => Transaction::STATUS_COMPLETED,
                'metadata' => $metadata,
                'processed_at' => now(),
            ]);
        });
    }

    public function refund(Transaction $originalTransaction, float $amount = null, string $reason = null): Transaction
    {
        if (!$originalTransaction->isCompleted()) {
            throw new \Exception('Can only refund completed transactions');
        }

        if ($originalTransaction->type !== Transaction::TYPE_PAYMENT) {
            throw new \Exception('Can only refund payment transactions');
        }

        $refundAmount = $amount ?? $originalTransaction->net_amount;

        if ($refundAmount > $originalTransaction->net_amount) {
            throw new \Exception('Refund amount exceeds original transaction amount');
        }

        $fee = $this->calculateFee($refundAmount, 'refund');
        $netAmount = bcsub((string) $refundAmount, (string) $fee, 3);

        return DB::transaction(function () use ($originalTransaction, $refundAmount, $fee, $netAmount, $reason) {
            $wallet = $originalTransaction->wallet;
            $wallet->addBalance($netAmount);

            return Transaction::create([
                'wallet_id' => $wallet->id,
                'user_id' => $wallet->user_id,
                'reference_id' => $originalTransaction->reference_id,
                'type' => Transaction::TYPE_REFUND,
                'operation' => Transaction::OPERATION_CREDIT,
                'amount' => $refundAmount,
                'currency' => $wallet->currency,
                'fee' => $fee,
                'net_amount' => $netAmount,
                'status' => Transaction::STATUS_COMPLETED,
                'metadata' => [
                    'original_transaction_id' => $originalTransaction->id,
                    'reason' => $reason,
                ],
                'processed_at' => now(),
            ]);
        });
    }

    private function createTransaction(
        Wallet $wallet,
        $reference,
        string $type,
        float $amount,
        string $operation,
        ?string $description = null
    ): Transaction {
        return Transaction::create([
            'wallet_id' => $wallet->id,
            'user_id' => $wallet->user_id,
            'reference_id' => $reference->uuid ?? null,
            'type' => $type,
            'operation' => $operation,
            'amount' => $amount,
            'currency' => $wallet->currency,
            'fee' => 0,
            'net_amount' => $amount,
            'status' => Transaction::STATUS_PENDING,
            'description' => $description,
        ]);
    }

    private function calculateFee(float $amount, string $type): float
    {
        $feeStructure = config('fatora.fees', [
            'knet' => 0.005,
            'emv' => 0.010,
            'card' => 0.025,
            'crypto' => 0.020,
            'withdrawal' => 0.005,
            'transfer' => 0.001,
            'payment' => 0.010,
            'refund' => 0,
        ]);

        $rate = $feeStructure[$type] ?? 0.010;
        $minFee = config("fatora.min_fees.{$type}", 0.100);
        $maxFee = config("fatora.max_fees.{$type}", 5.000);

        $fee = (float) bcmul((string) $amount, (string) $rate, 3);
        $fee = max($fee, $minFee);
        $fee = min($fee, $maxFee);

        return round($fee, 3);
    }

    public function getWalletBalance(Wallet $wallet): array
    {
        return [
            'uuid' => $wallet->uuid,
            'currency' => $wallet->currency,
            'balance' => $wallet->balance,
            'frozen_balance' => $wallet->frozen_balance,
            'available_balance' => $wallet->availableBalance(),
            'pending_balance' => $wallet->pending_balance,
            'daily_limit' => $wallet->daily_limit,
            'daily_spent' => $wallet->daily_spent,
            'daily_remaining' => $wallet->daily_limit ? bcsub($wallet->daily_limit, $wallet->daily_spent, 3) : null,
            'monthly_limit' => $wallet->monthly_limit,
            'monthly_spent' => $wallet->monthly_spent,
            'monthly_remaining' => $wallet->monthly_limit ? bcsub($wallet->monthly_limit, $wallet->monthly_spent, 3) : null,
        ];
    }
}
