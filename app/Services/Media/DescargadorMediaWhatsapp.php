<?php

namespace App\Services\Media;

use App\Infrastructure\Whatsapp\MetaWhatsAppSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DescargadorMediaWhatsapp
{
    /** @var list<string> */
    private const DOWNLOADABLE = ['image', 'audio', 'video', 'sticker', 'document'];

    public function esDescargable(string $messageType): bool
    {
        return in_array($messageType, self::DOWNLOADABLE, true);
    }

    /**
     * @param  array<string, mixed>  $rawMessage
     * @return array{url: string, local_url: string, mime: string|null}|null
     */
    public function descargar(string $waId, string $mediaKind, array $rawMessage): ?array
    {
        $token = MetaWhatsAppSettings::accessToken();
        if ($token === '') {
            Log::warning('DescargadorMediaWhatsapp: WHATSAPP_ACCESS_TOKEN vacío');

            return null;
        }

        $block = $rawMessage[$mediaKind] ?? null;
        if (! is_array($block)) {
            return null;
        }

        $mediaId = isset($block['id']) ? (string) $block['id'] : '';
        $downloadUrl = trim((string) ($block['link'] ?? $block['url'] ?? ''));

        if ($mediaId !== '' && ($downloadUrl === '' || $this->esUrlMeta($downloadUrl))) {
            $fromGraph = $this->obtenerUrlDesdeGraph($mediaId, $token);
            if ($fromGraph !== null) {
                $downloadUrl = $fromGraph;
            }
        }

        if ($downloadUrl === '') {
            return null;
        }

        try {
            $headers = ['User-Agent' => 'RomaAgent/1.0'];
            if ($this->esUrlMeta($downloadUrl) || $mediaId !== '') {
                $headers['Authorization'] = 'Bearer '.$token;
            }

            $response = Http::withHeaders($headers)->timeout(45)->get($downloadUrl);
            if (! $response->successful()) {
                Log::warning('DescargadorMediaWhatsapp: download failed', [
                    'wa_id' => $waId,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $mime = strtok($response->header('Content-Type') ?? '', ';') ?: null;
            if (! is_string($mime) || $mime === '') {
                $mime = is_string($block['mime_type'] ?? null) ? $block['mime_type'] : null;
            }
            if (! is_string($mime) || $mime === '') {
                $mime = $mediaKind === 'sticker' ? 'image/webp' : 'application/octet-stream';
            }

            $ext = $this->extensionDesdeMime($mime, $mediaKind);
            $safeId = substr(preg_replace('/[^a-zA-Z0-9._-]/', '_', $waId) ?? 'media', 0, 80);
            $filename = $safeId.'_'.$mediaKind.'_'.time().'.'.$ext;
            $path = 'inbound-media/'.$filename;

            Storage::disk('public')->put($path, $response->body());

            $localUrl = Storage::url($path);
            $publicBase = rtrim((string) config('app.public_url', config('app.url')), '/');
            $absoluteUrl = $publicBase.$localUrl;

            return [
                'url' => $absoluteUrl,
                'local_url' => $localUrl,
                'mime' => $mime,
            ];
        } catch (\Throwable $e) {
            Log::error('DescargadorMediaWhatsapp: exception', [
                'wa_id' => $waId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function obtenerUrlDesdeGraph(string $mediaId, string $token): ?string
    {
        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(20)
                ->get(MetaWhatsAppSettings::graphBaseUrl().'/'.$mediaId);

            if (! $response->successful()) {
                return null;
            }

            $url = $response->json('url');

            return is_string($url) && $url !== '' ? $url : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function esUrlMeta(string $url): bool
    {
        return str_contains($url, 'lookaside.fbsbx.com')
            || str_contains($url, 'graph.facebook.com')
            || str_contains($url, 'fbcdn.net');
    }

    private function extensionDesdeMime(string $mime, string $mediaKind): string
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

        return $map[strtolower($mime)] ?? ($mediaKind === 'sticker' ? 'webp' : 'bin');
    }
}
