<?php

namespace App\Infrastructure\Whatsapp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MetaWhatsAppClient
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function send(array $payload): array
    {
        if (! MetaWhatsAppSettings::isConfigured()) {
            throw new \RuntimeException(
                'WhatsApp directo no configurado. Agrega WHATSAPP_ACCESS_TOKEN y WHATSAPP_PHONE_NUMBER_ID al .env.'
            );
        }

        $traceId = 'trace_'.time().'_'.Str::random(7);
        $phone = preg_replace('/\D/', '', (string) ($payload['to'] ?? $payload['sender_phone'] ?? ''));
        $type = (string) ($payload['type'] ?? 'text');

        $graphPayload = match ($type) {
            'text' => $this->buildTextPayload($phone, $payload),
            'image' => $this->buildImagePayload($phone, $payload),
            default => throw new \RuntimeException('Tipo de mensaje no soportado: '.$type),
        };

        $url = MetaWhatsAppSettings::graphBaseUrl().'/'.MetaWhatsAppSettings::phoneNumberId().'/messages';

        Log::info('MetaWhatsAppClient: sending', [
            'phone' => $phone,
            'type' => $type,
            'trace_id' => $traceId,
        ]);

        $response = Http::withToken(MetaWhatsAppSettings::accessToken())
            ->acceptJson()
            ->timeout(20)
            ->connectTimeout(10)
            ->post($url, $graphPayload);

        $json = $response->json();
        $phoneNumberId = MetaWhatsAppSettings::phoneNumberId();

        if (! $response->successful()) {
            $error = is_array($json) ? ($json['error'] ?? $json) : ['message' => $response->body()];

            return [
                'ok' => false,
                'provider' => 'meta',
                'status' => 'failed',
                'meta_phone_id' => $phoneNumberId,
                'trace_id' => $traceId,
                'error' => $error,
                'meta_error' => $error,
            ];
        }

        $waId = is_array($json) ? ($json['messages'][0]['id'] ?? null) : null;
        if (! is_string($waId) || $waId === '') {
            throw new \RuntimeException('Meta respondió sin message id: '.json_encode($json));
        }

        return [
            'ok' => true,
            'provider' => 'meta',
            'status' => 'sent',
            'wa_id' => $waId,
            'provider_message_id' => $waId,
            'meta_phone_id' => $phoneNumberId,
            'trace_id' => $traceId,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function buildTextPayload(string $phone, array $payload): array
    {
        $body = (string) ($payload['text']['body'] ?? $payload['message_body'] ?? '');

        return [
            'messaging_product' => 'whatsapp',
            'to' => $phone,
            'type' => 'text',
            'text' => ['body' => $body],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function buildImagePayload(string $phone, array $payload): array
    {
        $image = is_array($payload['image'] ?? null) ? $payload['image'] : [];
        $link = (string) ($image['link'] ?? '');
        $caption = (string) ($image['caption'] ?? '');

        if ($link === '') {
            throw new \RuntimeException('Image link required for WhatsApp image message');
        }

        $graphImage = ['link' => $link];
        if ($caption !== '') {
            $graphImage['caption'] = $caption;
        }

        return [
            'messaging_product' => 'whatsapp',
            'to' => $phone,
            'type' => 'image',
            'image' => $graphImage,
        ];
    }
}
