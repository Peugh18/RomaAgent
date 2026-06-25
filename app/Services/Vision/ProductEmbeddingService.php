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

    /**
     * Genera el embedding para una variante específica basándose en sus características textuales.
     */
    public function generarEmbeddingVariante(ProductVariant $variant): ?array
    {
        $productName = $variant->product->name ?? 'Desconocido';
        $color = $variant->color ?? 'Desconocido';

        $product = $variant->product;
        $vision = $product->vision_profile ?? [];
        
        $tipoPrenda = ucfirst($vision['tipo_prenda'] ?? $product->category->name ?? 'Prenda');
        $nombreProducto = $product->name ?? '';
        
        $partes = [];
        
        // 1. Identificador Principal
        if (!empty($nombreProducto)) {
            if (stripos($nombreProducto, $tipoPrenda) === false) {
                $partes[] = "{$tipoPrenda} {$nombreProducto}";
            } else {
                $partes[] = ucfirst($nombreProducto);
            }
        } else {
            $partes[] = $tipoPrenda;
        }
        
        // 2. Color prioritario
        if (!empty($variant->color)) {
            $partes[] = "Color " . mb_strtolower($variant->color);
        }
        
        // 3. Patrón / Diseño
        if (!empty($vision['patron'])) {
            $partes[] = "Diseño " . mb_strtolower($vision['patron']);
        }
        
        // 4. Material
        if (!empty($vision['material_aparente'])) {
            $partes[] = "Material " . mb_strtolower($vision['material_aparente']);
        }
        
        // 5. Detalles (corte, cuello, mangas)
        if (isset($vision['detalles']) && is_array($vision['detalles']) && !empty($vision['detalles'])) {
            $detallesStr = implode(', ', array_map('mb_strtolower', $vision['detalles']));
            $partes[] = "Detalles: {$detallesStr}";
        }
        
        // 6. Keywords en contexto natural
        if (!empty($vision['keywords'])) {
            $kw = is_array($vision['keywords']) ? implode(', ', $vision['keywords']) : $vision['keywords'];
            $partes[] = "Ideal para " . mb_strtolower($kw);
        }
        
        // 7. Descripción limpia
        if (!empty($product->description)) {
            $desc = rtrim(trim($product->description), '.');
            if (!empty($desc)) {
                $partes[] = $desc;
            }
        }
        
        $descripcion = implode('. ', $partes) . '.';
        
        // Limpieza básica de espacios y puntuación repetida
        $descripcion = preg_replace('/\s+/', ' ', $descripcion);
        $descripcion = preg_replace('/\.{2,}/', '.', $descripcion);
        $descripcion = str_replace(' .', '.', $descripcion);
        $descripcion = trim($descripcion);

        return $this->generarEmbeddingTexto($descripcion);
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
