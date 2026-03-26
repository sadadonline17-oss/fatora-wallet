<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->string('event_type');
            $table->string('status_code')->nullable();
            $table->json('headers')->nullable();
            $table->json('payload');
            $table->text('signature')->nullable();
            $table->boolean('verified')->default(false);
            $table->text('response')->nullable();
            $table->enum('process_status', ['pending', 'processed', 'failed', 'duplicate'])->default('pending');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['provider', 'event_type']);
            $table->index('process_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_logs');
    }
};
