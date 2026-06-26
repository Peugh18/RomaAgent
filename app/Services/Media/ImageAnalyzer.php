<?php

namespace App\Services\Media;

use App\Exceptions\GeminiQuotaExceededException;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ConfiguracionAgente;
use App\Services\ServicioMediaProducto;
use App\Services\Vision\GarmentVisionService;
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
        private ProductEmbeddingService $embeddingService,
        private ServicioMediaProducto $mediaProducto,
        private GarmentVisionService $visionService
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
        // 2. EXTRACCIÓN DE CARACTERÍSTICAS DE LA PRENDA CON EL NUEVO MOTOR UNIFICADO
        $analysis = $this->visionService->analyze($imageUrl);

        if ($analysis === null || ! $analysis->esPrenda || empty($analysis->descripcionVectorial)) {
            Log::info('ImageAnalyzer: No se detectó una prenda clara en la imagen o falló la descripción. Usando fallback.');

            return $this->fallbackAnalysis($endpoint, $media, $mime, $apiKey, $captionCliente);
        }

        $descripcion = $analysis->descripcionVectorial;
        $colorExtraido = $analysis->colorPrincipal;

        Log::info('ImageAnalyzer: Descripción extraída de la imagen (Motor Unificado)', ['desc' => $descripcion]);

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

        $resultados = [];

        foreach ($variantesActivas as $variante) {
            $stockTotal = is_array($variante->sizes_stock) ? array_sum($variante->sizes_stock) : 0;
            if ($stockTotal <= 0 || ! is_array($variante->image_embedding)) {
                continue;
            }

            $similarity = $this->cosineSimilarity($embedding, $variante->image_embedding);

            // Penalización de Búsqueda Híbrida: Filtrar por Color (removida)
            if (! empty($colorExtraido) && ! empty($variante->color)) {
                $colorE = mb_strtolower(trim($colorExtraido));
                $colorV = mb_strtolower(trim($variante->color));

                // Si no hay coincidencia semántica de color básica, antes se penalizaba la similitud.
                // La penalización ha sido removida para mantener la similitud sin ajuste.
            }
            $resultados[] = [
                'variant' => $variante,
                'similarity' => $similarity,
            ];
        }

        usort($resultados, fn ($a, $b) => $b['similarity'] <=> $a['similarity']);

        $umbralExacto = 0.50; // Cambiado de 0.60 a 0.50 para ser más permisivo
        $umbralSimilar = 0.35; // Cambiado de 0.40 a 0.35

        $exactMatch = null;
        $similares = [];

        if (! empty($resultados)) {
            $mejor = $resultados[0];
            if ($mejor['similarity'] >= $umbralExacto) {
                $exactMatch = $mejor;
            } else {
                // Aquí se define cuántas opciones similares mostrar (cambiado a 2)
                foreach (array_slice($resultados, 0, 2) as $res) {
                    if ($res['similarity'] >= $umbralSimilar) {
                        $similares[] = $res;
                    }
                }
            }
        }

        if ($exactMatch !== null) {
            $variante = $exactMatch['variant'];
            Log::info('ImageAnalyzer: Producto encontrado por similitud vectorial EXACTA', [
                'id_producto' => $variante->product_id,
                'similitud' => $exactMatch['similarity'],
            ]);

            return [
                'caption' => 'Producto reconocido exactamente por vector descriptivo',
                'inbound_profile' => [
                    'encontrado' => true,
                    'matches' => [[
                        'id_producto' => $variante->product_id,
                        'nombre_vestido' => $variante->product->name ?? 'Desconocido',
                        'color' => $variante->color,
                        'similitud' => $exactMatch['similarity'],
                        'image_url' => $this->mediaProducto->resolveAbsolutePublicUrl($variante),
                    ]],
                    'tipo_mensaje' => 'producto',
                    'caption_cliente' => $captionCliente,
                ],
            ];
        }

        if (! empty($similares)) {
            Log::info('ImageAnalyzer: Productos similares encontrados por similitud vectorial', [
                'cantidad' => count($similares),
                'similitud_mejor' => $similares[0]['similarity'],
            ]);

            $matches = array_map(function ($res) {
                $variante = $res['variant'];

                return [
                    'id_producto' => $variante->product_id,
                    'nombre_vestido' => $variante->product->name ?? 'Desconocido',
                    'color' => $variante->color,
                    'similitud' => $res['similarity'],
                    'image_url' => $this->mediaProducto->resolveAbsolutePublicUrl($variante),
                ];
            }, $similares);

            return [
                'caption' => 'Productos similares reconocidos por vector descriptivo',
                'inbound_profile' => [
                    'encontrado' => false,
                    'matches' => $matches,
                    'tipo_mensaje' => 'producto',
                    'caption_cliente' => $captionCliente,
                ],
            ];
        }

        Log::info('ImageAnalyzer: No se encontró similitud vectorial aceptable. Se devolverá no encontrado.');

        return [
            'caption' => 'Producto no reconocido con certeza',
            'inbound_profile' => [
                'encontrado' => false,
                'matches' => [],
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
