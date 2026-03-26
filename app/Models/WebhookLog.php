<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebhookLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider',
        'event_type',
        'status_code',
        'headers',
        'payload',
        'signature',
        'verified',
        'response',
        'process_status',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'headers' => 'array',
            'payload' => 'array',
            'verified' => 'boolean',
            'processed_at' => 'datetime',
        ];
    }

    public const PROVIDER_KNET = 'knet';
    public const PROVIDER_EMV = 'emv';
    public const PROVIDER_CRYPTO = 'crypto';

    public const PROCESS_PENDING = 'pending';
    public const PROCESS_PROCESSED = 'processed';
    public const PROCESS_FAILED = 'failed';
    public const PROCESS_DUPLICATE = 'duplicate';

    public function markAsVerified(): void
    {
        $this->verified = true;
        $this->save();
    }

    public function markAsProcessed(string $response = null): void
    {
        $this->process_status = self::PROCESS_PROCESSED;
        $this->response = $response;
        $this->processed_at = now();
        $this->save();
    }

    public function markAsFailed(string $reason): void
    {
        $this->process_status = self::PROCESS_FAILED;
        $this->response = $reason;
        $this->save();
    }

    public function markAsDuplicate(): void
    {
        $this->process_status = self::PROCESS_DUPLICATE;
        $this->save();
    }
}
