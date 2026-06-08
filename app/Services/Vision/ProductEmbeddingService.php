<?php

namespace App\Services\Vision;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ConfiguracionAgente;
use App\Services\Media\BaseGeminiService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Genera embeddings textuales del catálogo con Gemini (gemini-embedding-001).
 */
class ProductEmbeddingService extends BaseGeminiService
{
    private const EMBEDDING_MODEL = 'gemini-embedding-001';

    public function __construct(
        ConfiguracionAgente $configuracion,
        private ResolutorUrlImagenVariante $resolutorUrl,
    ) {
        parent::__construct($configuracion);
    }

    public function generarEmbeddingVariante(ProductVariant $variant, ?string $imageUrl = null): ?array
    {
        $variant->loadMissing('product');

        return $this->generarEmbeddingTexto(
            $this->construirTextoEmbedding($variant, $imageUrl)
        );
    }

    /**
     * Embedding a partir del análisis visual de una imagen entrante (perfil JSON + caption).
     *
     * @param  array<string, mixed>  $inboundProfile
     */
    public function generarEmbeddingDesdeAnalisis(array $inboundProfile, ?string $caption = null): ?array
    {
        $partes = ['Imagen de clienta WhatsApp', 'moda femenina'];

        if ($caption) {
            $partes[] = 'caption: '.$caption;
        }

        foreach (['tipo_prenda', 'material_aparente', 'estilo', 'descripcion'] as $campo) {
            if (! empty($inboundProfile[$campo])) {
                $partes[] = $campo.': '.$inboundProfile[$campo];
            }
        }

        if (! empty($inboundProfile['colores_dominantes']) && is_array($inboundProfile['colores_dominantes'])) {
            $partes[] = 'colores: '.implode(', ', $inboundProfile['colores_dominantes']);
        }

        if (! empty($inboundProfile['keywords']) && is_array($inboundProfile['keywords'])) {
            $partes[] = 'keywords: '.implode(', ', $inboundProfile['keywords']);
        }

        $partes[] = 'perfil: '.json_encode($inboundProfile, JSON_UNESCAPED_UNICODE);

        return $this->generarEmbeddingTexto(implode('. ', $partes).'.');
    }

    public function generarEmbeddingProducto(Product $product, ?string $imageUrl = null): ?array
    {
        $texto = "Vestido {$product->name}. Prenda de moda femenina.";
        if ($imageUrl) {
            $texto .= " Imagen: {$imageUrl}.";
        }

        if (is_array($product->vision_profile) && $product->vision_profile !== []) {
            $texto .= ' Perfil: '.json_encode($product->vision_profile, JSON_UNESCAPED_UNICODE);
        }

        return $this->generarEmbeddingTexto($texto);
    }

    public function aplicarEmbeddingVariante(ProductVariant $variant, ?string $imageUrl = null): bool
    {
        $url = $imageUrl ?? $this->resolutorUrl->resolver($variant);
        $embedding = $this->generarEmbeddingVariante($variant, $url);
        if ($embedding === null) {
            return false;
        }

        $variant->update([
            'image_embedding' => $embedding,
            'embedding_at' => now(),
        ]);

        Log::info('Embedding generado para variante', [
            'variant_id' => $variant->id,
            'dimensions' => count($embedding),
        ]);

        return true;
    }

    /**
     * @return array{processed: int, success: int, failed: int, skipped: int}
     */
    public function procesarCatalogoCompleto(): array
    {
        $stats = [
            'processed' => 0,
            'success' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];

        Product::query()
            ->where('status', Product::ESTADO_DISPONIBLE)
            ->with('variants')
            ->chunk(10, function ($products) use (&$stats) {
                foreach ($products as $product) {
                    $stats['processed']++;

                    foreach ($product->variants as $variant) {
                        if ($variant->embedding_at && $variant->embedding_at->gt(now()->subDays(7))) {
                            $stats['skipped']++;

                            continue;
                        }

                        if ($this->aplicarEmbeddingVariante($variant)) {
                            $stats['success']++;
                        } else {
                            $stats['failed']++;
                        }
                    }
                }
            });

        return $stats;
    }

    private function construirTextoEmbedding(ProductVariant $variant, ?string $imageUrl): string
    {
        $product = $variant->product;
        $productName = $product?->name ?? 'producto';
        $color = $variant->color;
        $partes = ["Vestido {$productName}", "color {$color}", 'moda femenina'];

        if ($product && is_array($product->vision_profile) && $product->vision_profile !== []) {
            $partes[] = 'perfil producto: '.json_encode($product->vision_profile, JSON_UNESCAPED_UNICODE);
        }

        if (is_array($variant->color_profile) && $variant->color_profile !== []) {
            $partes[] = 'colorimetría: '.json_encode($variant->color_profile, JSON_UNESCAPED_UNICODE);
        }

        if ($product && is_array($product->tags_ia) && $product->tags_ia !== []) {
            $partes[] = 'tags: '.implode(', ', $product->tags_ia);
        }

        if ($imageUrl) {
            $partes[] = "referencia visual: {$imageUrl}";
        }

        return implode('. ', $partes).'.';
    }

    /**
     * @return list<float>|null
     */
    public function generarEmbeddingTexto(string $texto): ?array
    {
        $apiKey = $this->obtenerApiKey();
        if ($apiKey === null) {
            return null;
        }

        $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/'.self::EMBEDDING_MODEL.':embedContent';

        $payload = [
            'model' => 'models/'.self::EMBEDDING_MODEL,
            'content' => [
                'parts' => [
                    ['text' => $texto],
                ],
            ],
        ];

        try {
            $response = Http::withHeaders($this->headersGemini($apiKey))
                ->timeout(30)
                ->post($endpoint, $payload);

            if (! $response->successful()) {
                Log::error('ProductEmbeddingService: API error', [
                    'status' => $response->status(),
                    'body' => substr((string) $response->body(), 0, 300),
                ]);

                return null;
            }

            $values = $response->json('embedding.values');

            return is_array($values) ? $values : null;
        } catch (\Exception $e) {
            Log::error('ProductEmbeddingService: Exception', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
