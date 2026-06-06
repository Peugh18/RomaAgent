<?php

namespace App\Services;

use App\Support\ContratoMensajeWhatsapp;
use App\Support\RomaApiHeaders;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ServicioResolucionMediaEntrante
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{url: string, local_url: string|null, mime: string|null}|null
     */
    public function resolver(array $payload, string $messageType, string $waId): ?array
    {
        $existing = $payload['media_url'] ?? $payload['image_url'] ?? null;
        if (is_string($existing) && $existing !== '' && ! str_contains($existing, 'lookaside.fbsbx.com')) {
            $mirrored = $this->espejarRemoto($existing, $waId, $messageType, $payload['mime_type'] ?? null);

            return $mirrored ?? [
                'url' => $existing,
                'local_url' => null,
                'mime' => is_string($payload['mime_type'] ?? null) ? $payload['mime_type'] : null,
            ];
        }

        if (! in_array($messageType, ['image', 'audio', 'video', 'sticker', 'document'], true)) {
            return null;
        }

        $raw = $payload['raw'] ?? null;
        if (! is_array($raw)) {
            return null;
        }

        $baseUrl = rtrim((string) config('services.roma.url'), '/');
        if ($baseUrl === '') {
            return null;
        }

        try {
            $response = Http::withHeaders(RomaApiHeaders::forJsonPost())
                ->timeout(45)
                ->post($baseUrl.'/api/media/resolve-inbound', [
                    'wa_id' => $waId,
                    'media_kind' => $messageType,
                    'raw' => $raw,
                ]);

            $json = $response->json();
            if (! $response->successful() || ! is_array($json) || empty($json['public_url'])) {
                Log::warning('ServicioResolucionMediaEntrante: resolve failed', [
                    'wa_id' => $waId,
                    'type' => $messageType,
                    'status' => $response->status(),
                    'body' => is_string($response->body()) ? substr($response->body(), 0, 200) : $json,
                ]);

                return null;
            }

            $publicUrl = (string) $json['public_url'];
            $mime = is_string($json['mime_type'] ?? null) ? $json['mime_type'] : null;

            return $this->espejarRemoto($publicUrl, $waId, $messageType, $mime) ?? [
                'url' => $publicUrl,
                'local_url' => null,
                'mime' => $mime,
            ];
        } catch (\Throwable $e) {
            Log::error('ServicioResolucionMediaEntrante: exception', [
                'wa_id' => $waId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return array{url: string, local_url: string|null, mime: string|null}
     */
    public function aplicarAMetadata(array $metadata, array $resolved, string $messageType): array
    {
        $metadata['media_url'] = $resolved['url'];
        $metadata['type'] = $messageType;
        $metadata['whatsapp_message_type'] = $messageType;

        if ($resolved['mime']) {
            $metadata['mime_type'] = $resolved['mime'];
        }

        if (! empty($resolved['local_url'])) {
            $metadata['local_url'] = $resolved['local_url'];
        }

        if (in_array($messageType, ['image', 'sticker'], true)) {
            $metadata['image_url'] = $resolved['local_url'] ?? $resolved['url'];
        }

        return $metadata;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function inferirTipo(array $payload): string
    {
        $type = (string) ($payload['message_type'] ?? 'text');
        if (in_array($type, ['image', 'audio', 'video', 'sticker', 'document', 'location'], true)) {
            return $type;
        }

        if (ContratoMensajeWhatsapp::extraerUbicacion($payload) !== null) {
            return 'location';
        }

        $raw = $payload['raw'] ?? null;
        if (! is_array($raw)) {
            return $type;
        }

        $rawType = (string) ($raw['type'] ?? '');
        if ($rawType === 'voice') {
            return 'audio';
        }

        if (in_array($rawType, ['image', 'audio', 'video', 'sticker', 'document', 'location'], true)) {
            return $rawType;
        }

        return $type;
    }

    /**
     * @return array{url: string, local_url: string, mime: string|null}|null
     */
    protected function espejarRemoto(string $remoteUrl, string $waId, string $messageType, mixed $mime): ?array
    {
        try {
            $response = Http::withHeaders([
                'ngrok-skip-browser-warning' => 'true',
                'User-Agent' => 'RomaAgent/1.0',
            ])->timeout(45)->get($remoteUrl);

            if (! $response->successful()) {
                return null;
            }

            $contentType = $response->header('Content-Type') ?? 'application/octet-stream';
            $mimeType = is_string($mime) && $mime !== '' ? $mime : strtok($contentType, ';');
            $ext = $this->extensionFromMime((string) $mimeType, $messageType);
            $safeId = preg_replace('/[^a-zA-Z0-9._-]/', '_', $waId) ?? 'media';
            $filename = substr($safeId, 0, 80).'_'.$messageType.'_'.time().'.'.$ext;
            $path = 'inbound-media/'.$filename;

            Storage::disk('public')->put($path, $response->body());

            return [
                'url' => $remoteUrl,
                'local_url' => Storage::url($path),
                'mime' => (string) $mimeType,
            ];
        } catch (\Throwable $e) {
            Log::warning('ServicioResolucionMediaEntrante: espejarRemoto failed', [
                'url' => $remoteUrl,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    protected function extensionFromMime(string $mime, string $messageType): string
    {
        $map = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'audio/ogg' => 'ogg',
            'audio/mpeg' => 'mp3',
            'audio/mp4' => 'm4a',
            'video/mp4' => 'mp4',
            'application/pdf' => 'pdf',
        ];

        return $map[strtolower($mime)] ?? ($messageType === 'sticker' ? 'webp' : 'bin');
    }
}
