<?php

namespace App\Services\Media;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CargadorBytesMedia
{
    /**
     * @return array{bytes: string, mime: string}|null
     */
    public function desdeUrl(string $url): ?array
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (is_string($path) && preg_match('#/storage/(.+)$#', $path, $matches) === 1) {
            $fullPath = storage_path('app/public/'.$matches[1]);
            if (is_readable($fullPath)) {
                $mime = mime_content_type($fullPath);
                $mime = is_string($mime) && $mime !== '' ? $mime : 'application/octet-stream';

                return [
                    'bytes' => (string) file_get_contents($fullPath),
                    'mime' => $mime,
                ];
            }
        }

        try {
            $response = Http::withHeaders([
                'ngrok-skip-browser-warning' => 'true',
                'User-Agent' => 'RomaAgent/1.0',
            ])->timeout(45)->get($url);

            if (! $response->successful()) {
                Log::warning('CargadorBytesMedia: fetch failed', [
                    'status' => $response->status(),
                    'url' => $url,
                ]);

                return null;
            }

            $mime = $response->header('Content-Type') ?? 'application/octet-stream';
            $mime = is_string($mime) ? strtok($mime, ';') : 'application/octet-stream';

            return [
                'bytes' => $response->body(),
                'mime' => (string) $mime,
            ];
        } catch (\Throwable $e) {
            Log::warning('CargadorBytesMedia: exception', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
