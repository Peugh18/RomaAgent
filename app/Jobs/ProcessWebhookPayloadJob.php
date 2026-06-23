<?php

namespace App\Jobs;

use App\Actions\ProcessIncomingMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

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
    public function handle(ProcessIncomingMessage $processIncoming): void
    {
        try {
            $processIncoming->execute($this->payload);
        } catch (\Throwable $e) {
            Log::error('WhatsappWebhook: inbound failed in Queue', [
                'wa_id' => $this->payload['wa_id'] ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
