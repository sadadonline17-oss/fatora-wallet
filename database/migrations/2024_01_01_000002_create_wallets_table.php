<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('currency', 3)->default('KWD');
            $table->decimal('balance', 15, 3)->default(0);
            $table->decimal('pending_balance', 15, 3)->default(0);
            $table->decimal('available_balance', 15, 3)->default(0);
            $table->string('account_number', 20)->unique();
            $table->string('pin_hash')->nullable();
            $table->enum('status', ['active', 'suspended', 'closed', 'pending_verification'])->default('active');
            $table->timestamp('last_transaction_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['user_id', 'currency']);
            $table->index('account_number');
            $table->index('status');
        });

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('wallets');
    }
};
