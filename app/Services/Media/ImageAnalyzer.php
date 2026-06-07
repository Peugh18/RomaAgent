<?php

namespace App\Services\Media;

use App\Services\ConfiguracionAgente;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ImageAnalyzer
{
    /**
     * Analyze an image (caption, colors) using Gemini models with image URL input.
     * Returns an associative array with keys: caption, colors (string), ocr (string|null).
     */
    public function analyzeUrl(string $imageUrl): ?array
    {
        $apiKey = config('services.gemini.key') ?? env('GEMINI_API_KEY');
        $model = config('services.gemini.model', 'gemini-1.5-flash');

        if (empty($apiKey)) {
            // Fallback: DB-stored API key used by the agent configuration
            try {
                $apiKey = (new ConfiguracionAgente)->obtenerApiKey();
            } catch (\Throwable $e) {
                $apiKey = '';
            }
            if (empty($apiKey)) {
                Log::warning('ImageAnalyzer: GEMINI_API_KEY not configured');

                return null;
            }
        }

        $endpoint = sprintf('https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s', $model, $apiKey);

        $prompt = 'Describe brevemente la imagen para ayudar en ventas (1-2 frases). Identifica colores dominantes y cualquier texto visible (OCR) si es relevante.';

        // Fetch image bytes
        $imgResp = Http::timeout(30)->get($imageUrl);
        if (! $imgResp->successful()) {
            Log::warning('ImageAnalyzer: unable to fetch image', [
                'status' => $imgResp->status(),
                'url' => $imageUrl,
            ]);

            return null;
        }

        $bytes = $imgResp->body();
        $mime = $imgResp->header('Content-Type') ?? 'image/jpeg';
        $mime = is_string($mime) ? $mime : 'image/jpeg';

        $payload = [
            'contents' => [[
                'parts' => [
                    ['text' => $prompt],
                    [
                        'inline_data' => [
                            'mime_type' => $mime,
                            'data' => base64_encode($bytes),
                        ],
                    ],
                ],
            ]],
        ];

        try {
            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->timeout(45)
                ->post($endpoint, $payload);

            if (! $response->successful()) {
                Log::error('ImageAnalyzer: Gemini error', [
                    'status' => $response->status(),
                    'body' => substr((string) $response->body(), 0, 300),
                ]);

                return null;
            }

            $data = $response->json();
            $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
            $text = is_string($text) ? trim($text) : '';

            if ($text === '') {
                return null;
            }

            return [
                'caption' => $text,
            ];
        } catch (\Throwable $e) {
            Log::error('ImageAnalyzer exception', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
