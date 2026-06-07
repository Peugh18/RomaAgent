<?php

namespace Tests\Feature;

use App\Actions\Pedidos\ActualizarPedidoVenta;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActualizarPedidoVentaPrecioTest extends TestCase
{
    use RefreshDatabase;

    public function test_ignora_precio_sugerido_por_agente_si_hay_producto_en_catalogo(): void
    {
        $customer = Customer::factory()->create();

        Product::query()->create([
            'name' => 'Mariela',
            'price' => 180,
            'status' => Product::ESTADO_DISPONIBLE,
        ]);

        $sale = app(ActualizarPedidoVenta::class)->handle($customer, [
            'product_name' => 'Mariela',
            'unit_price' => 50,
            'quantity' => 3,
            'total_amount' => 150,
        ]);

        $this->assertSame(180.0, (float) $sale->unit_price);
        $this->assertSame(540.0, (float) $sale->total_amount);
    }
}
