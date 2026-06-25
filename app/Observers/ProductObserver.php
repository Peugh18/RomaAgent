<?php

namespace App\Observers;

use App\Models\Product;
use App\Services\Vision\ProductEmbeddingService;
use App\Support\InvalidatesPromptCache;

class ProductObserver
{
    use InvalidatesPromptCache;

    /** Campos cuyo cambio invalida los embeddings de las variantes del producto. */
    private const RELEVANT_FIELDS = [
        'name',
        'description',
        'tags_ia',
        'vision_profile',
        'category_id',
    ];

    public function saved(Product $product): void
    {
        $this->invalidarCachePrompt();

        // wasChanged() está disponible después del save() y contiene los campos persistidos.
        // getDirty() siempre está vacío dentro de saved(), por eso usamos wasChanged().
        $changed = array_keys($product->getChanges());
        $relevant = array_intersect($changed, self::RELEVANT_FIELDS);

        if (empty($relevant)) {
            return;
        }

        // Si cambió la categoría, recargamos la relación para tener el nombre actualizado
        if (in_array('category_id', $changed)) {
            $product->load('category');
        }

        $service = app(ProductEmbeddingService::class);
        foreach ($product->variants as $variant) {
            $embedding = $service->generarEmbeddingVariante($variant);
            if (is_array($embedding) && ! empty($embedding)) {
                // updateQuietly evita volver a disparar ProductVariantObserver::saved()
                $variant->updateQuietly([
                    'image_embedding' => $embedding,
                    'embedding_at' => now(),
                ]);
            }
        }
    }

    public function deleted(Product $product): void
    {
        $this->invalidarCachePrompt();
    }
}
