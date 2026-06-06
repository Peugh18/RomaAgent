<?php

namespace App\Jobs;

use App\Infrastructure\Whatsapp\RomaWhatsappClient;
use App\Models\Message;
use App\Support\MessageBroadcaster;
use App\Support\WhatsappSendError;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Queue\Queueable;

class SendWhatsappMessageJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(protected Message $message) {}

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [5, 20, 60, 180, 420];
    }

    public function handle(RomaWhatsappClient $client): void
    {
        Log::info('SendWhatsappMessageJob: processing', [
            'id' => $this->message->id,
            'phone' => $this->message->phone_number,
        ]);

        try {
            $waId = $this->message->message_id;
            if (! str_starts_with($waId, 'out_') && ! str_starts_with($waId, 'temp_')) {
                $waId = 'out_'.uniqid();
            } elseif (str_starts_with($waId, 'temp_')) {
                $waId = 'out_'.uniqid();
            }

            $metadata = is_array($this->message->metadata) ? $this->message->metadata : [];
            $imageUrl = $metadata['image_url'] ?? null;

            $response = $client->sendMessage(
                $this->message->phone_number,
                $this->message->content,
                $waId,
                is_string($imageUrl) ? $imageUrl : null,
                $metadata
            );

            $providerWaId = (string) ($response['wa_id'] ?? $response['message_id'] ?? '');
            if ($providerWaId === '' || ! str_starts_with($providerWaId, 'wamid.')) {
                throw new \RuntimeException(
                    'Roma API accepted request without Meta wa_id. Response: '.json_encode($response)
                );
            }

            $this->message->update([
                'message_id' => $providerWaId,
                'status' => 'sent',
                'metadata' => array_merge($metadata, [
                    'roma_api_response' => $response,
                    'sent_via_job' => true,
                    'meta_wa_id' => $providerWaId,
                ]),
            ]);

            MessageBroadcaster::broadcast($this->message->fresh(), 'SendWhatsappMessageJob');
        } catch (\Throwable $e) {
            Log::error('SendWhatsappMessageJob: failed', [
                'id' => $this->message->id,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            if (WhatsappSendError::isPermanent($e)) {
                $this->markAsFailed(WhatsappSendError::userMessage($e));

                return;
            }

            if ($this->attempts() >= $this->tries) {
                $this->markAsFailed(WhatsappSendError::userMessage($e));
            }

            throw $e;
        }
    }

    public function failed(?\Throwable $exception): void
    {
        $fresh = $this->message->fresh();
        if ($fresh && $fresh->status === 'pending') {
            $this->markAsFailed($exception?->getMessage() ?? 'No se pudo enviar el mensaje');
        }
    }

    protected function markAsFailed(string $error): void
    {
        $metadata = is_array($this->message->metadata) ? $this->message->metadata : [];
        $this->message->update([
            'status' => 'failed',
            'metadata' => array_merge($metadata, [
                'send_error' => $error,
            ]),
        ]);

        MessageBroadcaster::broadcast($this->message->fresh(), 'SendWhatsappMessageJob');
    }
}
