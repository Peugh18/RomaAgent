<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessWebhookPayloadJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public array $payload
    ) {}

    /**
     * Execute the job.
     */
    public function handle(\App\Actions\ProcessIncomingMessage $processIncoming): void
    {
        try {
            $processIncoming->execute($this->payload);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('WhatsappWebhook: inbound failed in Queue', [
                'wa_id' => $this->payload['wa_id'] ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
