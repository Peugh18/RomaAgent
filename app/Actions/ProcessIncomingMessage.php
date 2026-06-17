<?php

namespace App\Actions;

use App\Models\Customer;
use App\Models\Message;
use App\Services\ServicioResolucionMediaEntrante;
use App\Support\ContratoMensajeWhatsapp;
use App\Support\MessageBroadcaster;
use Illuminate\Support\Facades\Log;

class ProcessIncomingMessage
{
    private const MEDIA_TYPES = [
        'image', 'audio', 'video', 'document', 'sticker', 'location',
        'interactive_button_reply', 'interactive_list_reply',
    ];

    public function __construct(
        private ServicioResolucionMediaEntrante $mediaService,
    ) {}

    public function execute(array $payload): Message
    {
        $messageType = $this->mediaService->inferirTipo($payload);
        $payload['message_type'] = $messageType;
        $contentPreview = ContratoMensajeWhatsapp::inboundContent($payload);

        $directionRaw = $this->normalizeDirection($payload['direction'] ?? 'incoming');
        $phoneNumber = $this->extractPhoneNumber($payload);

        $metadata = ContratoMensajeWhatsapp::inboundMetadata($payload);
        $resolved = $this->mediaService->resolver($payload, $messageType, $this->extractMessageId($payload));

        if ($resolved !== null) {
            $metadata = $this->mediaService->aplicarAMetadata($metadata, $resolved, $messageType);
        }

        $esMensajeNuevo = ! Message::query()
            ->where('message_id', $this->extractMessageId($payload))
            ->exists();

        $message = Message::updateOrCreate(
            ['message_id' => $this->extractMessageId($payload)],
            [
                'phone_number' => $phoneNumber,
                'customer_name' => $payload['sender_name'] ?? $payload['customer_name'] ?? $payload['name'] ?? null,
                'content' => is_string($contentPreview) ? $contentPreview : json_encode($contentPreview),
                'direction' => $this->normalizeDirection($payload['direction'] ?? 'incoming'),
                'status' => $payload['status'] ?? 'delivered',
                'whatsapp_timestamp' => $payload['timestamp'] ?? now(),
                'metadata' => $metadata,
            ]
        );

        if ($message->direction === 'incoming') {
            $customer = Customer::resolverDesdeMensaje(
                $phoneNumber,
                $message->customer_name
            );
            $customer->update([
                'last_inbound_at' => now(),
                'reminder_3min_sent_at' => null,
                'reminder_15min_sent_at' => null,
            ]);
        }

        MessageBroadcaster::broadcast($message, 'ProcessIncomingMessage');

        Log::info('Roma inbound: message stored', [
            'id' => $message->id,
            'phone' => $phoneNumber,
            'type' => $messageType,
        ]);

        // Solo procesar IA en mensajes entrantes nuevos (idempotencia ante reintentos del webhook)
        if ($esMensajeNuevo && $message->direction === 'incoming') {
            event(new \App\Events\InboundMessageReceived($message, $esMensajeNuevo));
        }

        return $message;
    }

    public function isStatusUpdate(array $payload): bool
    {
        $directionRaw = $this->normalizeDirection($payload['direction'] ?? 'incoming');
        $messageType = $this->mediaService->inferirTipo($payload);
        $contentPreview = ContratoMensajeWhatsapp::inboundContent($payload);

        $hasInboundPayload = $directionRaw === 'incoming' && (
            $contentPreview !== ''
            || in_array($messageType, self::MEDIA_TYPES, true)
            || ! empty($payload['interactive'])
            || ! empty($payload['image_url'])
            || ! empty($payload['media_url'])
            || ! empty($payload['location'])
            || ContratoMensajeWhatsapp::extraerUbicacion($payload) !== null
        );

        return ! $hasInboundPayload && (
            (isset($payload['event']) && $payload['event'] === 'status')
            || (isset($payload['status']) && in_array($payload['status'], ['sent', 'delivered', 'read', 'failed'], true))
        );
    }

    private function normalizeDirection(?string $direction): string
    {
        return match ($direction) {
            'inbound' => 'incoming',
            'outbound' => 'outgoing',
            default => $direction ?? 'incoming',
        };
    }

    private function extractPhoneNumber(array $payload): string
    {
        return $payload['from']
            ?? $payload['phone_number']
            ?? $payload['sender_phone']
            ?? throw new \InvalidArgumentException('Phone number missing');
    }

    private function extractMessageId(array $payload): string
    {
        return $payload['wa_id']
            ?? $payload['message_id']
            ?? $payload['id']
            ?? uniqid('msg_');
    }
}
