<?php

namespace App\Observers;

use App\Models\ProductVariant;
use App\Services\Vision\ProductEmbeddingService;
use App\Support\InvalidatesPromptCache;

class ProductVariantObserver
{
    use InvalidatesPromptCache;

    /** Campos cuyo cambio invalida el embedding de la variante. */
    private const RELEVANT_FIELDS = [
        'color',
    ];

    public function saved(ProductVariant $variant): void
    {
        $this->sincronizarProducto($variant);

        // wasChanged() contiene los campos persistidos después del save().
        // getDirty() estaría vacío en este punto.
        $changed = array_keys($variant->getChanges());
        $relevant = array_intersect($changed, self::RELEVANT_FIELDS);

        if (empty($relevant)) {
            return;
        }

        $service = app(ProductEmbeddingService::class);
        $embedding = $service->generarEmbeddingVariante($variant);
        if (is_array($embedding) && ! empty($embedding)) {
            // updateQuietly: evita disparar este mismo observer de nuevo.
            $variant->updateQuietly([
                'image_embedding' => $embedding,
                'embedding_at' => now(),
            ]);
        }
    }

    public function deleted(ProductVariant $variant): void
    {
        $this->sincronizarProducto($variant);
        // Optionally clean up embedding if needed; here we just leave as is.
    }

    private function sincronizarProducto(ProductVariant $variant): void
    {
        $product = $variant->product()->with('variants')->first();

        if ($product === null) {
            return;
        }

        $product->sincronizarEstadoPorStock();

        $this->invalidarCachePrompt();
    }
}
