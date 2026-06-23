<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Support\ValidadorPrecioPedido;
use PHPUnit\Framework\TestCase;

class ValidadorPrecioPedidoTest extends TestCase
{
    public function test_usa_precio_catalogo_ignorando_agente(): void
    {
        $product = new Product([
            'price' => 180,
            'discount_active' => false,
        ]);

        $precio = ValidadorPrecioPedido::resolverPrecioUnitario($product, 50);

        $this->assertSame(180.0, $precio);
    }

    public function test_aplica_promo_activa(): void
    {
        $product = new Product([
            'price' => 180,
            'discount' => 30,
            'discount_active' => true,
        ]);

        // When no agent suggestion is provided, the method should apply the active promo.
        $precio = ValidadorPrecioPedido::resolverPrecioUnitario($product, null);

        $this->assertSame(150.0, $precio);
    }

    public function test_calcula_total_desde_cantidad_y_envio(): void
    {
        $total = ValidadorPrecioPedido::calcularTotal(180, 3);

        $this->assertSame(540.0, $total);
    }
}
