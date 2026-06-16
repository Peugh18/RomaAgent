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

        $sugerido = $sugeridoPorAgente !== null ? (float) $sugeridoPorAgente : null;
        $precioCatalogo = (float) $product->price;
        $precioTikTok = $product->price_tiktok !== null ? (float) $product->price_tiktok : null;
        
        $promo = null;
        if ($product->descuentoPromoActivo()) {
            $promo = $product->precioNormalConPromo();
        }

        if ($sugerido !== null) {
            $sugeridoRedondeado = round($sugerido, 2);
            $esValido = $sugeridoRedondeado === round($precioCatalogo, 2)
                || ($precioTikTok !== null && $sugeridoRedondeado === round($precioTikTok, 2))
                || ($promo !== null && $sugeridoRedondeado === round($promo, 2));

            if ($esValido) {
                return max(0, $sugeridoRedondeado);
            }
        }

        $precioFinal = $precioCatalogo;
        if ($promo !== null) {
            $precioFinal = $promo;
        }

        return round(max(0, $precioFinal), 2);
    }

    public static function calcularTotal(float $unitPrice, int $quantity, float $deliveryCost): float
    {
        return round(max(0, $unitPrice) * max(1, $quantity) + max(0, $deliveryCost), 2);
    }
}
