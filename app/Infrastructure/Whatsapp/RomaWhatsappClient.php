<?php

namespace App\Infrastructure\Whatsapp;

use App\Support\ContratoMensajeWhatsapp;
use Illuminate\Support\Facades\Log;

class RomaWhatsappClient
{
    public function __construct(
        private MetaWhatsAppClient $metaClient,
    ) {}

    public function sendMessage(
        string $phone,
        string $body,
        string $waId,
        ?string $imageUrl = null,
        ?array $metadata = null
    ): array {
        $payload = ContratoMensajeWhatsapp::buildOutbound($phone, $body, $waId, $imageUrl, $metadata);

        Log::info('RomaWhatsappClient: sending to Meta', [
            'phone' => $phone,
            'type' => $payload['type'] ?? 'text',
        ]);

        $json = $this->metaClient->send($payload);

        if (($json['meta_phone_id'] ?? null) === '') {
            throw new \RuntimeException(
                'Meta respondió sin meta_phone_id. Revisa WHATSAPP_PHONE_NUMBER_ID en el .env.'
            );
        }

        if (array_key_exists('ok', $json) && $json['ok'] === false) {
            $detail = self::formatErrorDetail($json);

            throw new \RuntimeException('Meta/WhatsApp rejected message: '.$detail);
        }

        return $json;
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private static function formatErrorDetail(array $json): string
    {
        if (isset($json['error']) && is_array($json['error'])) {
            return json_encode($json['error']);
        }

        $detail = $json['error'] ?? $json['meta_error'] ?? $json['message'] ?? 'Unknown error';

        return is_string($detail) ? $detail : json_encode($detail);
    }
}
