<?php

namespace App\Services\Vision;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Búsqueda por similitud vectorial (cosine similarity en PHP).
 */
class VectorSearchService
{
    /**
     * @param  array<float>  $queryEmbedding
     * @return Collection<int, array{variant: ProductVariant, similarity: float}>
     */
    public function buscarSimilares(array $queryEmbedding, int $limit = 10, float $threshold = 0.7): Collection
    {
        if ($queryEmbedding === []) {
            return collect();
        }

        $variantes = ProductVariant::query()
            ->whereNotNull('image_embedding')
            ->whereHas('product', fn ($q) => $q->where('status', Product::ESTADO_DISPONIBLE))
            ->with('product')
            ->get();

        return $variantes
            ->map(function (ProductVariant $variant) use ($queryEmbedding) {
                $embedding = $variant->image_embedding;
                if (! is_array($embedding) || $embedding === []) {
                    return null;
                }

                $similarity = $this->cosineSimilarity($queryEmbedding, $embedding);

                return [
                    'variant' => $variant,
                    'similarity' => $similarity,
                ];
            })
            ->filter()
            ->filter(fn (array $row) => $row['similarity'] >= $threshold)
            ->sortByDesc('similarity')
            ->take($limit)
            ->values();
    }

    /**
     * @param  array<float>  $a
     * @param  array<float>  $b
     */
    public function cosineSimilarity(array $a, array $b): float
    {
        if (count($a) !== count($b) || $a === [] || $b === []) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0; $i < count($a); $i++) {
            $dotProduct += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }

        if ($normA == 0.0 || $normB == 0.0) {
            return 0.0;
        }

        return $dotProduct / (sqrt($normA) * sqrt($normB));
    }

    /**
     * @param  array<string, mixed>  $inboundProfile
     */
    public function buscarPorAnalisisCliente(array $inboundProfile, ?string $caption, ProductEmbeddingService $embeddingService): Collection
    {
        $embedding = $embeddingService->generarEmbeddingDesdeAnalisis($inboundProfile, $caption);

        if ($embedding === null) {
            Log::warning('VectorSearchService: no se pudo generar embedding del análisis cliente');

            return collect();
        }

        return $this->buscarSimilares($embedding, 5, 0.55);
    }
}
