<?php

namespace App\Services\Vision;

use App\Models\ProductVariant;
use App\Services\ConfiguracionAgente;
use App\Services\Media\BaseGeminiService;
use App\Services\Media\CargadorBytesMedia;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Servicio para generar embeddings usando el modelo de texto de Gemini.
 */
class ProductEmbeddingService extends BaseGeminiService
{
    public function __construct(
        ConfiguracionAgente $configuracion,
        private CargadorBytesMedia $cargador,
    ) {
        parent::__construct($configuracion);
    }

    /**
     * Genera el embedding vectorial de un texto descriptivo.
     *
     * @param  string  $texto  El texto a vectorizar.
     * @return array<float>|null Array plano de floats o null si falla.
     */
    public function generarEmbeddingTexto(string $texto): ?array
    {
        $apiKey = $this->obtenerApiKey();
        if ($apiKey === null) {
            return null;
        }

        $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-embedding-2:embedContent';

        $payload = [
            'content' => [
                'parts' => [
                    [
                        'text' => $texto,
                    ],
                ],
            ],
        ];

        return $this->ejecutarConRetry(function () use ($endpoint, $payload, $apiKey) {
            $response = Http::withHeaders($this->headersGemini($apiKey))
                ->timeout($this->timeout)
                ->post($endpoint.'?key='.$apiKey, $payload);

            $data = $this->procesarRespuestaApi($response);
            if ($data === null) {
                return null;
            }

            return $data['embedding']['values'] ?? null;
        });
    }

    public function generarEmbeddingVariante(ProductVariant $variant): ?array
    {
        $product = $variant->product;
        $vision = $product->vision_profile ?? [];

        // El nuevo pipeline unificado siempre debe generar una descripcion_vectorial fluida
        $descripcionVectorial = $vision['descripcion_vectorial'] ?? null;

        if (empty($descripcionVectorial)) {
            Log::warning('ProductEmbeddingService: Variante sin descripcion_vectorial. Usando fallback básico.', [
                'variant_id' => $variant->id,
            ]);
            // Fallback súper básico por si hay productos viejos sin el nuevo vision_profile
            $nombreProducto = $product->name ?? 'Prenda';
            $color = $variant->color ?? 'Desconocido';
            $descripcionVectorial = "{$nombreProducto} color {$color}";
        }

        return $this->generarEmbeddingTexto($descripcionVectorial);
    }

    /**
     * Procesa todas las variantes del catálogo con stock que no tienen embedding, o todas si se fuerza.
     *
     * @param  bool  $force  Si es true, reprocesa todos los productos aunque ya tengan embedding.
     * @return array{processed: int, success: int, failed: int, skipped: int} Estadísticas del proceso.
     */
    public function procesarCatalogoCompleto(bool $force = false): array
    {
        $stats = [
            'processed' => 0,
            'success' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];

        // Buscar variantes activas con stock > 0
        $query = ProductVariant::whereHas('product', function ($q) {
            $q->where('status', 'disponible');
        });

        if (! $force) {
            $query->whereNull('image_embedding');
        }

        $variants = $query->get();

        foreach ($variants as $variant) {
            // Verificar stock
            $stockTotal = is_array($variant->sizes_stock) ? array_sum($variant->sizes_stock) : 0;
            if ($stockTotal <= 0) {
                $stats['skipped']++;

                continue;
            }

            $stats['processed']++;

            try {
                $embedding = $this->generarEmbeddingVariante($variant);

                if (is_array($embedding) && ! empty($embedding)) {
                    $variant->update([
                        'image_embedding' => $embedding,
                        'embedding_at' => now(),
                    ]);
                    $stats['success']++;
                } else {
                    $stats['failed']++;
                }
            } catch (\Throwable $e) {
                Log::error('ProductEmbeddingService: error al procesar la variante '.$variant->id, [
                    'error' => $e->getMessage(),
                ]);
                $stats['failed']++;
            }
        }

        return $stats;
    }
}
