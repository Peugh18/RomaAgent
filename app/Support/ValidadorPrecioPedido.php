<?php

namespace App\Support;

use App\Models\Product;

class ValidadorPrecioPedido
{
    /**
     * Precio unitario canónico desde catálogo. Ignora valores sugeridos por el agente si hay producto.
     */
    public static function resolverPrecioUnitario(?Product $product, mixed $sugeridoPorAgente = null): float
    {
        if ($product === null) {
            return max(0, (float) ($sugeridoPorAgente ?? 0));
        }

        $precio = (float) $product->price;

        if ($product->descuentoPromoActivo()) {
            $promo = $product->precioNormalConPromo();
            if ($promo !== null) {
                $precio = $promo;
            }
        }

        return round(max(0, $precio), 2);
    }

    public static function calcularTotal(float $unitPrice, int $quantity, float $deliveryCost): float
    {
        return round(max(0, $unitPrice) * max(1, $quantity) + max(0, $deliveryCost), 2);
    }
}
