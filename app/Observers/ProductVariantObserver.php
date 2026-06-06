<?php

namespace App\Observers;

use App\Models\CompanySetting;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Cache;

class ProductVariantObserver
{
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

        $settingsId = CompanySetting::query()->value('id');
        if ($settingsId !== null) {
            Cache::forget('contexto_prompt_completo_'.$settingsId);
        }
    }
}
