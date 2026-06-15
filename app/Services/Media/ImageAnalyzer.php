<?php

namespace App\Services\Media;

use App\Exceptions\GeminiQuotaExceededException;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ConfiguracionAgente;
use App\Services\Vision\OptimizedVisionPrompts;
use App\Services\Vision\ProductEmbeddingService;
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
        private ProductEmbeddingService $embeddingService
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

        $modelo = $this->obtenerModelo();
        $endpoint = $this->construirEndpoint($modelo);
        $mime = $this->normalizarMimeImagen($media['mime']);

        // 1. PASADA RÁPIDA: Detección de comprobante
        $promptComprobante = OptimizedVisionPrompts::promptDetectorComprobante();
        $payloadComprobante = $this->buildPayload($promptComprobante, $media, $mime);

        $resComprobante = $this->ejecutarConRetry(function () use ($endpoint, $payloadComprobante, $apiKey, $captionCliente) {
            return $this->callGeminiApi($endpoint, $payloadComprobante, $apiKey, $captionCliente);
        });

        if ($resComprobante && ($resComprobante['inbound_profile']['es_comprobante'] ?? false)) {
            Log::info('ImageAnalyzer: Comprobante detectado.');

            return [
                'caption' => 'Comprobante detectado',
                'inbound_profile' => [
                    'tipo_mensaje' => 'comprobante',
                    'es_comprobante' => true,
                    'encontrado' => false,
                    'caption_cliente' => $captionCliente,
                ],
            ];
        }

        // 2. EXTRACCIÓN DE CARACTERÍSTICAS DE LA PRENDA
        $promptPrenda = OptimizedVisionPrompts::promptExtractorCaracteristicasPrenda();
        $payloadPrenda = $this->buildPayload($promptPrenda, $media, $mime);

        $resPrenda = $this->ejecutarConRetry(function () use ($endpoint, $payloadPrenda, $apiKey, $captionCliente) {
            return $this->callGeminiApi($endpoint, $payloadPrenda, $apiKey, $captionCliente);
        });

        $esPrenda = $resPrenda['inbound_profile']['es_prenda'] ?? false;
        $descripcion = $resPrenda['inbound_profile']['descripcion_vectorial'] ?? null;

        if (! $esPrenda || empty($descripcion)) {
            Log::info('ImageAnalyzer: No se detectó una prenda clara en la imagen o falló la descripción. Usando fallback.');

            return $this->fallbackAnalysis($endpoint, $media, $mime, $apiKey, $captionCliente);
        }

        Log::info('ImageAnalyzer: Descripción extraída de la imagen', ['desc' => $descripcion]);

        // 3. OBTENER EMBEDDING DEL TEXTO DESCRIPTIVO
        $embedding = $this->embeddingService->generarEmbeddingTexto($descripcion);
        if ($embedding === null) {
            Log::warning('ImageAnalyzer: No se pudo generar el embedding del texto. Usando fallback LLM.');

            return $this->fallbackAnalysis($endpoint, $media, $mime, $apiKey, $captionCliente);
        }

        // 4. BÚSQUEDA VECTORIAL EN MEMORIA (Cosine Similarity de Texto)
        $variantesActivas = ProductVariant::whereHas('product', function ($q) {
            $q->where('status', Product::ESTADO_DISPONIBLE);
        })
            ->whereNotNull('image_embedding')
            ->with('product')
            ->get();

        $bestSimilarity = -1.0;
        $bestVariant = null;

        foreach ($variantesActivas as $variante) {
            $stockTotal = is_array($variante->sizes_stock) ? array_sum($variante->sizes_stock) : 0;
            if ($stockTotal <= 0 || ! is_array($variante->image_embedding)) {
                continue;
            }

            $similarity = $this->cosineSimilarity($embedding, $variante->image_embedding);
            if ($similarity > $bestSimilarity) {
                $bestSimilarity = $similarity;
                $bestVariant = $variante;
            }
        }

        // El umbral para textos suele ser alto porque los textos similares (ej. vestidos) puntúan alto.
        // Lo ponemos en 0.45 para ser permisivos con los textos.
        $umbral = 0.45;
        if ($bestVariant && $bestSimilarity >= $umbral) {
            Log::info('ImageAnalyzer: Producto encontrado por similitud vectorial de TEXTO', [
                'id_producto' => $bestVariant->product_id,
                'similitud' => $bestSimilarity,
            ]);

            return [
                'caption' => 'Producto reconocido por vector descriptivo',
                'inbound_profile' => [
                    'encontrado' => true,
                    'id_producto' => $bestVariant->product_id,
                    'nombre_vestido' => $bestVariant->product->name ?? 'Desconocido',
                    'color' => $bestVariant->color,
                    'tipo_mensaje' => 'producto',
                    'similitud' => $bestSimilarity,
                    'caption_cliente' => $captionCliente,
                ],
            ];
        }

        Log::info('ImageAnalyzer: No se encontró similitud vectorial aceptable (max: '.$bestSimilarity.'). Se devolverá no encontrado.');

        return [
            'caption' => 'Producto no reconocido con certeza',
            'inbound_profile' => [
                'encontrado' => false,
                'id_producto' => null,
                'nombre_vestido' => null,
                'color' => null,
                'tipo_mensaje' => 'producto',
                'caption_cliente' => $captionCliente,
            ],
        ];
    }

    private function fallbackAnalysis(string $endpoint, array $media, string $mime, string $apiKey, string $captionCliente): ?array
    {
        $variantesActivas = ProductVariant::whereHas('product', function ($q) {
            $q->where('status', Product::ESTADO_DISPONIBLE);
        })
            ->with('product')
            ->get();

        $inventarioTexto = $variantesActivas->map(function ($variante) {
            $stockTotal = is_array($variante->sizes_stock) ? array_sum($variante->sizes_stock) : 0;
            if ($stockTotal <= 0) {
                return null;
            }

            $nombre = $variante->product->name ?? 'Desconocido';

            return "- ID_Producto: {$variante->product_id} | Vestido: {$nombre} | Color: {$variante->color} | Stock: {$stockTotal}";
        })->filter()->join("\n");

        $prompt = OptimizedVisionPrompts::promptAnalisisCliente($captionCliente, $inventarioTexto);
        $payload = $this->buildPayload($prompt, $media, $mime);

        return $this->ejecutarConRetry(function () use ($endpoint, $payload, $apiKey, $captionCliente) {
            return $this->callGeminiApi($endpoint, $payload, $apiKey, $captionCliente);
        });
    }

    private function buildPayload(string $prompt, array $media, string $mime): array
    {
        return [
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
    }

    private function cosineSimilarity(array $vec1, array $vec2): float
    {
        $dotProduct = 0.0;
        $norm1 = 0.0;
        $norm2 = 0.0;
        $count = min(count($vec1), count($vec2));

        for ($i = 0; $i < $count; $i++) {
            $v1 = (float) $vec1[$i];
            $v2 = (float) $vec2[$i];

            $dotProduct += $v1 * $v2;
            $norm1 += $v1 * $v1;
            $norm2 += $v2 * $v2;
        }

        if ($norm1 == 0.0 || $norm2 == 0.0) {
            return 0.0;
        }

        return $dotProduct / (sqrt($norm1) * sqrt($norm2));
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
