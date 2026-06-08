<?php

namespace App\Observers;

use App\Models\ProductVariant;
use App\Support\InvalidatesPromptCache;

class ProductVariantObserver
{
    use InvalidatesPromptCache;

    public function saved(ProductVariant $variant): void
    {
        $this->sincronizarProducto($variant);
    }

    public function deleted(ProductVariant $variant): void
    {
        $this->sincronizarProducto($variant);
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
