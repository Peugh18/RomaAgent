<?php

namespace App\Infrastructure\Whatsapp;

use App\Support\ContratoMensajeWhatsapp;
use App\Support\RomaApiHeaders;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RomaWhatsappClient
{
    public function sendMessage(
        string $phone,
        string $body,
        string $waId,
        ?string $imageUrl = null,
        ?array $metadata = null
    ): array {
        $baseUrl = rtrim((string) config('services.roma.url'), '/');
        $romaUrl = $baseUrl.'/api/messages';
        $payload = ContratoMensajeWhatsapp::buildOutbound($phone, $body, $waId, $imageUrl, $metadata);

        Log::info('RomaWhatsappClient: sending', [
            'phone' => $phone,
            'url' => $romaUrl,
        ]);

        $response = Http::withHeaders(RomaApiHeaders::forJsonPost())
            ->timeout(20)
            ->connectTimeout(10)
            ->post($romaUrl, $payload);

        $json = $response->json();

        if (is_array($json) && ($json['meta_phone_id'] ?? null) === '') {
            throw new \RuntimeException(
                'roma-api respondió sin meta_phone_id. Configura WHATSAPP_PHONE_NUMBER_ID en roma-api y reinicia el servicio.'
            );
        }

        if (! $response->successful()) {
            $detail = self::formatErrorDetail($json, $response->body(), $response->status());

            throw new \RuntimeException('Roma API send failed ('.$response->status().'): '.$detail);
        }

        if (is_array($json) && array_key_exists('ok', $json) && $json['ok'] === false) {
            $detail = self::formatErrorDetail($json, null, $response->status());

            throw new \RuntimeException('Meta/WhatsApp rejected message: '.$detail);
        }

        return is_array($json) ? $json : [];
    }

    /**
     * @param  array<string, mixed>|null  $json
     */
    private static function formatErrorDetail(?array $json, ?string $fallbackBody, int $status): string
    {
        if (! is_array($json)) {
            return $fallbackBody ?: 'Unknown error';
        }

        if (isset($json['error']) && is_array($json['error'])) {
            return json_encode($json['error']);
        }

        $detail = $json['error'] ?? $json['meta_error'] ?? $json['message'] ?? $fallbackBody ?? 'Unknown error';

        return is_string($detail) ? $detail : json_encode($detail);
    }
}
