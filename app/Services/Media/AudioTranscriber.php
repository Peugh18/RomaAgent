<?php

namespace App\Services\Media;

use App\Services\ConfiguracionAgente;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AudioTranscriber
{
    public function __construct(
        private ConfiguracionAgente $configuracion,
        private CargadorBytesMedia $cargador,
    ) {}

    /**
     * Transcribe audio de WhatsApp. Usa Gemini (misma API del agente) y OpenAI Whisper como respaldo.
     */
    public function transcribeFromUrl(string $url, string $language = 'es'): ?string
    {
        $media = $this->cargador->desdeUrl($url);
        if ($media === null) {
            return null;
        }

        $texto = $this->transcribirConGemini($media['bytes'], $media['mime'], $language);
        if ($texto !== null && $texto !== '' && $texto !== '[inaudible]') {
            return $texto;
        }

        $whisper = $this->transcribirConWhisper($media['bytes'], $media['mime'], $language);

        return ($whisper !== null && $whisper !== '') ? $whisper : null;
    }

    private function transcribirConGemini(string $bytes, string $mime, string $language): ?string
    {
        $apiKey = $this->configuracion->obtenerApiKey();
        if (empty($apiKey)) {
            Log::warning('AudioTranscriber: sin API key Gemini configurada');

            return null;
        }

        $modelo = $this->configuracion->obtenerModelo();
        $endpoint = sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
            $modelo,
            $apiKey,
        );

        $prompt = <<<PROMPT
Transcribe exactamente lo que dice la persona en este audio de WhatsApp.
Idioma esperado: {$language}.
Reglas:
- Devuelve SOLO la transcripción, sin comillas ni explicaciones.
- Conserva nombres, tallas, colores, direcciones y montos tal como se escuchan.
- Si no se entiende nada, responde exactamente: [inaudible]
PROMPT;

        $payload = [
            'contents' => [[
                'parts' => [
                    ['text' => $prompt],
                    [
                        'inline_data' => [
                            'mime_type' => $this->normalizarMimeAudio($mime),
                            'data' => base64_encode($bytes),
                        ],
                    ],
                ],
            ]],
            'generationConfig' => [
                'temperature' => 0.1,
                'maxOutputTokens' => 1024,
            ],
        ];

        try {
            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->timeout(60)
                ->post($endpoint, $payload);

            if (! $response->successful()) {
                Log::error('AudioTranscriber: Gemini error', [
                    'status' => $response->status(),
                    'body' => substr((string) $response->body(), 0, 300),
                ]);

                return null;
            }

            $data = $response->json();
            $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
            $text = is_string($text) ? trim($text) : '';

            return $text !== '' ? $text : null;
        } catch (\Throwable $e) {
            Log::error('AudioTranscriber: Gemini exception', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function transcribirConWhisper(string $bytes, string $mime, string $language): ?string
    {
        $apiKey = config('services.openai.key') ?? env('OPENAI_API_KEY');
        if (empty($apiKey)) {
            return null;
        }

        try {
            $extension = match ($this->normalizarMimeAudio($mime)) {
                'audio/mpeg', 'audio/mp3' => 'mp3',
                'audio/mp4', 'audio/m4a' => 'm4a',
                'audio/wav' => 'wav',
                default => 'ogg',
            };

            $response = Http::withToken($apiKey)
                ->asMultipart()
                ->timeout(60)
                ->post('https://api.openai.com/v1/audio/transcriptions', [
                    [
                        'name' => 'file',
                        'contents' => $bytes,
                        'filename' => 'whatsapp_audio_'.time().'.'.$extension,
                    ],
                    [
                        'name' => 'model',
                        'contents' => 'whisper-1',
                    ],
                    [
                        'name' => 'language',
                        'contents' => $language,
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('AudioTranscriber: Whisper error', [
                    'status' => $response->status(),
                    'body' => substr((string) $response->body(), 0, 200),
                ]);

                return null;
            }

            $json = $response->json();

            return is_string($json['text'] ?? null) ? trim((string) $json['text']) : null;
        } catch (\Throwable $e) {
            Log::warning('AudioTranscriber: Whisper exception', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function normalizarMimeAudio(string $mime): string
    {
        $mime = strtolower(trim(strtok($mime, ';')));

        return match (true) {
            str_contains($mime, 'ogg') => 'audio/ogg',
            str_contains($mime, 'mpeg') || str_contains($mime, 'mp3') => 'audio/mpeg',
            str_contains($mime, 'mp4') || str_contains($mime, 'm4a') => 'audio/mp4',
            str_contains($mime, 'wav') => 'audio/wav',
            str_contains($mime, 'webm') => 'audio/webm',
            default => 'audio/ogg',
        };
    }
}
