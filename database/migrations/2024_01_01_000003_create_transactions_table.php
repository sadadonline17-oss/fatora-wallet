<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_topups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 15, 3);
            $table->string('currency', 3);
            $table->enum('gateway', ['knet', 'paytabs', 'myfatoorah']);
            $table->string('gateway_transaction_id')->nullable();
            $table->string('transaction_id')->unique();
            $table->string('payment_url')->nullable();
            $table->enum('status', [
                'pending', 'processing', 'completed', 'failed', 'cancelled', 'refunded'
            ])->default('pending');
            $table->decimal('fees', 15, 3)->default(0);
            $table->decimal('net_amount', 15, 3);
            $table->json('metadata')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            
            $table->index(['wallet_id', 'status']);
            $table->index('transaction_id');
            $table->index('gateway_transaction_id');
            $table->index('gateway');
            $table->index('created_at');
        });

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->onDelete('cascade');
            $table->foreignId('topup_id')->nullable()->constrained('wallet_topups')->onDelete('set null');
            $table->enum('type', ['credit', 'debit', 'transfer', 'refund', 'fee', 'reserve', 'release']);
            $table->decimal('amount', 15, 3);
            $table->decimal('balance_before', 15, 3);
            $table->decimal('balance_after', 15, 3);
            $table->text('description')->nullable();
            $table->string('reference_id')->nullable();
            $table->string('reference_type')->nullable();
            $table->enum('status', ['pending', 'completed', 'failed', 'reversed'])->default('completed');
            $table->json('metadata')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            
            $table->index(['wallet_id', 'type']);
            $table->index(['wallet_id', 'created_at']);
            $table->index('reference_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('wallet_topups');
    }
};
