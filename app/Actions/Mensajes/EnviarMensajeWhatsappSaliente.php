<?php

namespace App\Actions\Mensajes;

use App\Jobs\SendWhatsappMessageJob;
use App\Models\Message;
use App\Support\MessageBroadcaster;

class EnviarMensajeWhatsappSaliente
{
    /**
     * @param  array<string, mixed>  $metadataExtra
     */
    public function handle(
        string $phoneNumber,
        string $content,
        ?string $customerName = null,
        ?string $imageUrl = null,
        array $metadataExtra = [],
        int $delaySeconds = 0,
    ): Message {
        $metadata = array_merge([
            'type' => $imageUrl ? 'image' : 'text',
            'generated_by' => $metadataExtra['generated_by'] ?? 'ai_agent',
        ], $metadataExtra);

        if ($imageUrl) {
            $metadata['image_url'] = $imageUrl;
        }

        $message = Message::query()->create([
            'message_id' => 'out_'.uniqid(),
            'phone_number' => $phoneNumber,
            'customer_name' => $customerName,
            'content' => $content,
            'direction' => 'outgoing',
            'status' => 'pending',
            'whatsapp_timestamp' => now(),
            'metadata' => $metadata,
        ]);

        MessageBroadcaster::broadcast($message, 'EnviarMensajeWhatsappSaliente');

        $job = SendWhatsappMessageJob::dispatch($message);
        if ($delaySeconds > 0) {
            $job->delay(now()->addSeconds($delaySeconds));
        }

        return $message;
    }
}
