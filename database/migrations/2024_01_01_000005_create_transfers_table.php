<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('sender_wallet_id')->constrained('wallets')->onDelete('cascade');
            $table->foreignId('receiver_wallet_id')->constrained('wallets')->onDelete('cascade');
            $table->foreignId('sender_transaction_id')->nullable()->constrained('transactions')->onDelete('set null');
            $table->foreignId('receiver_transaction_id')->nullable()->constrained('transactions')->onDelete('set null');
            $table->decimal('amount', 20, 3);
            $table->string('currency', 3)->default('KWD');
            $table->decimal('fee', 20, 3)->default(0.000);
            $table->string('status');
            $table->string('qr_code')->nullable();
            $table->string('pin_code')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamps();

            $table->index(['sender_wallet_id', 'status']);
            $table->index(['receiver_wallet_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfers');
    }
};
