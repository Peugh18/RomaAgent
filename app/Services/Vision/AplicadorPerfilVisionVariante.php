<?php

namespace App\Services\Vision;

use App\Exceptions\GeminiQuotaExceededException;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ServicioMediaProducto;
use App\Support\Vision\PerfilVisionFallback;
use Illuminate\Support\Facades\Log;

class AplicadorPerfilVisionVariante
{
    public function __construct(
        private GeneradorPerfilVisionCatalogo $generador,
        private ServicioMediaProducto $media,
    ) {}

    /**
     * @return array{gemini: bool, fallback: bool}
     *
     * @throws GeminiQuotaExceededException
     */
    public function aplicar(ProductVariant $variant, bool $usarGemini = true): array
    {
        $variant->loadMissing('product');
        $product = $variant->product;

        $imageUrl = $this->resolverUrlImagen($variant);
        $geminiOk = false;

        if ($usarGemini && $imageUrl !== null) {
            $geminiOk = $this->aplicarConGemini($variant, $product, $imageUrl);
        }

        $fallbackOk = $this->asegurarFallback($variant, $product);

        return [
            'gemini' => $geminiOk,
            'fallback' => $fallbackOk,
        ];
    }

    public function aplicarSoloFallback(ProductVariant $variant): bool
    {
        $variant->loadMissing('product');

        return $this->asegurarFallback($variant, $variant->product);
    }

    private function resolverUrlImagen(ProductVariant $variant): ?string
    {
        return $this->media->resolveAbsolutePublicUrl($variant)
            ?? $this->media->resolvePublicUrl($variant);
    }

    private function aplicarConGemini(ProductVariant $variant, ?Product $product, string $imageUrl): bool
    {
        $perfiles = $this->generador->generarPerfilesVariante($variant, $imageUrl);

        if ($perfiles === null) {
            Log::warning('AplicadorPerfilVisionVariante: Gemini no devolvió perfiles', [
                'variant_id' => $variant->id,
            ]);

            return false;
        }

        $ok = false;

        if (is_array($perfiles['color'] ?? null) && $perfiles['color'] !== []) {
            $variant->update([
                'color_profile' => $perfiles['color'],
                'color_profile_at' => now(),
            ]);
            $ok = true;
        }

        if ($product !== null && is_array($perfiles['producto'] ?? null) && $perfiles['producto'] !== []) {
            $product->update([
                'vision_profile' => $perfiles['producto'],
                'vision_profile_at' => now(),
            ]);
            $ok = true;
        }

        return $ok;
    }

    private function asegurarFallback(ProductVariant $variant, ?Product $product): bool
    {
        $variant->refresh();
        $product?->refresh();

        $aplicado = false;

        if (empty($variant->color_profile)) {
            PerfilVisionFallback::aplicarColor($variant);
            $aplicado = true;
        }

        if ($product !== null && empty($product->vision_profile)) {
            PerfilVisionFallback::aplicarProducto($product);
            $aplicado = true;
        }

        return $aplicado;
    }
}
