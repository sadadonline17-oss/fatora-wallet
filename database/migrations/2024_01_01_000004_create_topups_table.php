<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('topups', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('wallet_id')->constrained()->onDelete('cascade');
            $table->string('provider');
            $table->decimal('amount', 20, 3);
            $table->string('currency', 3)->default('KWD');
            $table->decimal('fee', 20, 3)->default(0.000);
            $table->decimal('net_amount', 20, 3);
            $table->string('status');
            $table->string('checkout_url')->nullable();
            $table->string('payment_id')->nullable();
            $table->string('track_id')->nullable();
            $table->string('auth_code')->nullable();
            $table->string('result_code')->nullable();
            $table->string('result_message')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['wallet_id', 'created_at']);
            $table->index(['provider', 'payment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topups');
    }
};
