<?php

namespace App\Services\Pedidos;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\NormalizadorStockTallas;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ServicioStockPedido
{
    public function decrementarPorVentaConfirmada(
        ?int $productVariantId,
        string $size,
        int $quantity = 1,
    ): void {
        if ($productVariantId === null) {
            return;
        }

        DB::transaction(function () use ($productVariantId, $size, $quantity): void {
            /** @var ProductVariant|null $variant */
            $variant = ProductVariant::query()->lockForUpdate()->find($productVariantId);

            if ($variant === null) {
                return;
            }

            $sizeKey = mb_strtoupper(trim($size)) ?: NormalizadorStockTallas::defaultSizeKey();
            if (NormalizadorStockTallas::esTallaEstandar($sizeKey)) {
                $sizeKey = NormalizadorStockTallas::defaultSizeKey();
            }
            
            $stock = $variant->sizes_stock ?? [];

            if (! array_key_exists($sizeKey, $stock)) {
                throw new RuntimeException("No hay stock registrado para la talla {$sizeKey}.");
            }

            $disponible = max(0, (int) $stock[$sizeKey]);

            if ($disponible < $quantity) {
                throw new RuntimeException("Stock insuficiente para {$sizeKey}: quedan {$disponible}.");
            }

            $stock[$sizeKey] = $disponible - $quantity;
            $variant->update(['sizes_stock' => $stock]);

            /** @var Product $product */
            $product = $variant->product;
            $product->sincronizarEstadoPorStock();
        });
    }
}
