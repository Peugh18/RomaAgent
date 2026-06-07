<?php

namespace App\Services\Media;

use App\Exceptions\GeminiQuotaExceededException;
use App\Services\ConfiguracionAgente;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Servicio de análisis de imágenes usando Gemini API.
 *
 * Hereda de BaseGeminiService para reutilizar:
 * - Obtención de API key y modelo
 * - Normalización de MIME types
 * - Manejo de errores HTTP
 * - Detección de errores de cuota (429)
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
     * Analiza una imagen (caption / OCR breve) con el mismo modelo Gemini del agente.
     *
     * @return array{caption: string}|null
     */
    public function analyzeUrl(string $imageUrl): ?array
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

        $modelo = $this->obtenerModelo();
        $endpoint = $this->construirEndpoint($modelo);

        $prompt = <<<'PROMPT'
Describe brevemente la imagen para ayudar en ventas por WhatsApp (1-2 frases en español).
Si parece comprobante de pago (Yape, Plin, transferencia, voucher), indícalo claramente.
Identifica colores dominantes si es foto de producto y cualquier texto visible relevante.
PROMPT;

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
                'temperature' => 0.2,
                'maxOutputTokens' => 512,
            ],
        ];

        return $this->ejecutarConRetry(function () use ($endpoint, $payload, $apiKey) {
            return $this->callGeminiApi($endpoint, $payload, $apiKey);
        });
    }

    /**
     * Realiza la llamada HTTP a la API de Gemini.
     *
     * @return array{caption: string}|null
     *
     * @throws GeminiQuotaExceededException
     */
    private function callGeminiApi(string $endpoint, array $payload, string $apiKey): ?array
    {
        $response = Http::withHeaders($this->headersGemini($apiKey))
            ->timeout($this->timeout)
            ->post($endpoint, $payload);

        $data = $this->procesarRespuestaApi($response);
        $text = $this->extraerTextoRespuesta($data);

        if ($text === null || $text === '') {
            return null;
        }

        return ['caption' => $text];
    }
}
