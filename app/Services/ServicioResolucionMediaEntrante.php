<?php

namespace App\Services;

use App\Infrastructure\Whatsapp\MetaWhatsAppSettings;
use App\Services\Media\DescargadorMediaWhatsapp;
use App\Support\ContratoMensajeWhatsapp;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ServicioResolucionMediaEntrante
{
    public function __construct(
        private DescargadorMediaWhatsapp $descargadorMedia,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{url: string, local_url: string|null, mime: string|null}|null
     */
    public function resolver(array $payload, string $messageType, string $waId): ?array
    {
        $localUrl = $this->extraerRutaStorageLocal($payload);
        if ($localUrl !== null) {
            return $this->resolverDesdeStorageLocal($localUrl, $payload);
        }

        $existing = $payload['media_url'] ?? $payload['image_url'] ?? null;
        if (is_string($existing) && $existing !== '') {
            if ($this->esUrlStoragePropio($existing)) {
                return $this->resolverDesdeUrlStorage($existing, $payload);
            }

            if (! str_contains($existing, 'lookaside.fbsbx.com')) {
                $mirrored = $this->espejarRemoto($existing, $waId, $messageType, $payload['mime_type'] ?? null);

                return $mirrored ?? [
                    'url' => $existing,
                    'local_url' => null,
                    'mime' => is_string($payload['mime_type'] ?? null) ? $payload['mime_type'] : null,
                ];
            }
        }

        if (! in_array($messageType, ['image', 'audio', 'video', 'sticker', 'document'], true)) {
            return null;
        }

        $raw = $payload['raw'] ?? null;
        if (! is_array($raw)) {
            return null;
        }

        if (MetaWhatsAppSettings::isConfigured()) {
            $descargado = $this->descargadorMedia->descargar($waId, $messageType, $raw);
            if ($descargado !== null) {
                return [
                    'url' => $descargado['url'],
                    'local_url' => $descargado['local_url'],
                    'mime' => $descargado['mime'],
                ];
            }
        }

        return null;
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
     * @param  array<string, mixed>  $payload
     * @return array{url: string, local_url: string, mime: string|null}
     */
    protected function resolverDesdeStorageLocal(string $localUrl, array $payload): array
    {
        $publicBase = rtrim((string) config('app.public_url', config('app.url')), '/');
        $absoluteUrl = $publicBase.$localUrl;

        return [
            'url' => $absoluteUrl,
            'local_url' => $localUrl,
            'mime' => is_string($payload['mime_type'] ?? null) ? $payload['mime_type'] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{url: string, local_url: string, mime: string|null}
     */
    protected function resolverDesdeUrlStorage(string $url, array $payload): array
    {
        $path = parse_url($url, PHP_URL_PATH);
        $localUrl = is_string($path) && str_starts_with($path, '/storage/') ? $path : $url;

        return [
            'url' => $url,
            'local_url' => $localUrl,
            'mime' => is_string($payload['mime_type'] ?? null) ? $payload['mime_type'] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function extraerRutaStorageLocal(array $payload): ?string
    {
        $localUrl = $payload['local_url'] ?? null;
        if (is_string($localUrl) && str_starts_with($localUrl, '/storage/')) {
            return $localUrl;
        }

        $imageUrl = $payload['image_url'] ?? null;
        if (is_string($imageUrl) && str_starts_with($imageUrl, '/storage/')) {
            return $imageUrl;
        }

        return null;
    }

    protected function esUrlStoragePropio(string $url): bool
    {
        if (str_contains($url, '/storage/inbound-media/')) {
            return true;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (is_string($path) && str_starts_with($path, '/storage/inbound-media/')) {
            return true;
        }

        $hosts = array_filter([
            parse_url((string) config('app.public_url'), PHP_URL_HOST),
            parse_url((string) config('app.url'), PHP_URL_HOST),
        ]);

        $urlHost = parse_url($url, PHP_URL_HOST);

        return is_string($urlHost)
            && in_array($urlHost, $hosts, true)
            && is_string($path)
            && str_starts_with($path, '/storage/');
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
