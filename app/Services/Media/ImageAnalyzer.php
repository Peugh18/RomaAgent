<?php

namespace App\Services\Media;

use App\Exceptions\GeminiQuotaExceededException;
use App\Services\ConfiguracionAgente;
use App\Services\Vision\OptimizedVisionPrompts;
use App\Support\Vision\ParseadorRespuestaJsonGemini;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Servicio de análisis de imágenes usando Gemini API.
 *
 * @extends BaseGeminiService
 */
class ImageAnalyzer extends BaseGeminiService
{
    public function __construct(
        ConfiguracionAgente $configuracion,
        private CargadorBytesMedia $cargador,
    ) {
        parent::__construct($configuracion);
    }

    /**
     * @param  array<string, mixed>  $contexto
     * @return array{
     *   caption: string,
     *   inbound_profile: array<string, mixed>
     * }|null
     */
    public function analyzeUrl(string $imageUrl, array $contexto = []): ?array
    {
        $apiKey = $this->obtenerApiKey();
        if ($apiKey === null) {
            return null;
        }

        $media = $this->cargador->desdeUrl($imageUrl);
        if ($media === null) {
            Log::warning('ImageAnalyzer: no se pudo cargar la imagen', ['url' => $imageUrl]);

            return null;
        }

        $captionCliente = trim((string) ($contexto['caption_cliente'] ?? ''));

        // Usar prompts optimizados del sistema de visión
        $prompt = OptimizedVisionPrompts::promptAnalisisCliente($captionCliente);

        $modelo = $this->obtenerModelo();
        $endpoint = $this->construirEndpoint($modelo);
        $mime = $this->normalizarMimeImagen($media['mime']);

        $payload = [
            'contents' => [[
                'parts' => [
                    ['text' => $prompt],
                    [
                        'inline_data' => [
                            'mime_type' => $mime,
                            'data' => base64_encode($media['bytes']),
                        ],
                    ],
                ],
            ]],
            'generationConfig' => [
                'temperature' => 0.15,
                'maxOutputTokens' => 2048,
                'responseMimeType' => 'application/json',
            ],
        ];

        return $this->ejecutarConRetry(function () use ($endpoint, $payload, $apiKey, $captionCliente) {
            return $this->callGeminiApi($endpoint, $payload, $apiKey, $captionCliente);
        });
    }

    /**
     * @return array{caption: string, inbound_profile: array<string, mixed>}|null
     *
     * @throws GeminiQuotaExceededException
     */
    private function callGeminiApi(string $endpoint, array $payload, string $apiKey, string $captionCliente): ?array
    {
        $response = Http::withHeaders($this->headersGemini($apiKey))
            ->timeout($this->timeout)
            ->post($endpoint, $payload);

        $data = $this->procesarRespuestaApi($response);
        $text = $this->extraerTextoRespuesta($data);
        $profile = ParseadorRespuestaJsonGemini::parse($text);

        $finishReason = $data['candidates'][0]['finishReason'] ?? null;
        if ($profile === null || ($finishReason === 'MAX_TOKENS' && $text !== null)) {
            Log::warning('ImageAnalyzer: JSON de visión incompleto o inválido', [
                'finish_reason' => $finishReason,
                'text_len' => $text !== null ? strlen($text) : 0,
                'preview' => $text !== null ? substr($text, 0, 120) : null,
            ]);
        }

        if ($profile === null) {
            if ($text === null || $text === '') {
                return null;
            }

            return [
                'caption' => $captionCliente !== '' ? $captionCliente : 'imagen de producto sin análisis completo',
                'inbound_profile' => [
                    'tipo' => 'otro',
                    'descripcion_prenda' => $text,
                    'caption_cliente' => $captionCliente,
                ],
            ];
        }

        if (($profile['tipo'] ?? '') === 'producto' && empty($profile['tipo_prenda'])) {
            $profile['tipo_prenda'] = 'vestido';
        }

        if ($captionCliente !== '') {
            $profile['caption_cliente'] = $captionCliente;
        }

        $caption = (string) ($profile['descripcion_prenda']
            ?? $profile['texto_visible']
            ?? $captionCliente
            ?? 'imagen recibida');

        return [
            'caption' => $caption,
            'inbound_profile' => $profile,
        ];
    }
}
