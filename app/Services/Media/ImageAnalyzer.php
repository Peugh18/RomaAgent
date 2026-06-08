<?php

namespace App\Services\Media;

use App\Exceptions\GeminiQuotaExceededException;
use App\Services\ConfiguracionAgente;
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

        $prompt = <<<PROMPT
Analiza la imagen para ventas por WhatsApp. Responde SOLO JSON válido (sin markdown).

Si es comprobante de pago (Yape, Plin, transferencia), tipo=comprobante.
Si es foto de producto/prenda o captura de red social con un artículo del catálogo, tipo=producto e ignora textos de marketing de la plataforma.
Si es captura de pantalla de redes, marca es_captura_redes=true.

Esquema:
{
  "tipo": "producto|comprobante|otro",
  "es_comprobante": false,
  "es_captura_redes": false,
  "tipo_prenda": "vestido|blusa|pantalón|accesorio|otro|null",
  "material_aparente": "punto|algodón|null",
  "color_dominante": "color principal",
  "colores_dominantes": ["color1", "color2"],
  "descripcion_prenda": "1 frase sobre la prenda visible",
  "texto_visible": "texto OCR relevante o vacío",
  "caption_cliente": "{$captionCliente}"
}
PROMPT;

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
                'maxOutputTokens' => 1024,
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

        if ($profile === null) {
            if ($text === null || $text === '') {
                return null;
            }

            return [
                'caption' => $text,
                'inbound_profile' => [
                    'tipo' => 'otro',
                    'descripcion_prenda' => $text,
                    'caption_cliente' => $captionCliente,
                ],
            ];
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
