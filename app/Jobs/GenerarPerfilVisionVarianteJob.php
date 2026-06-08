<?php

namespace App\Jobs;

use App\Models\ProductVariant;
use App\Services\ServicioMediaProducto;
use App\Services\Vision\GeneradorPerfilVisionCatalogo;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class GenerarPerfilVisionVarianteJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public int $variantId) {}

    public function handle(
        GeneradorPerfilVisionCatalogo $generador,
        ServicioMediaProducto $media,
    ): void {
        $variant = ProductVariant::query()->with('product')->find($this->variantId);
        if ($variant === null) {
            return;
        }

        $imageUrl = $media->resolveAbsolutePublicUrl($variant)
            ?? $media->resolvePublicUrl($variant);

        if ($imageUrl === null || $imageUrl === '') {
            Log::warning('GenerarPerfilVisionVarianteJob: variante sin foto', [
                'variant_id' => $variant->id,
            ]);

            return;
        }

        $generador->aplicarPerfilColor($variant, $imageUrl);

        $product = $variant->product;
        if ($product !== null && empty($product->vision_profile)) {
            $generador->aplicarPerfilProducto($product, $imageUrl);
        }
    }
}
