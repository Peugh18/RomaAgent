<?php

namespace Tests\Unit;

use App\Enums\SaleStatus;
use App\Models\Sale;
use App\Support\PlantillaMensajePedido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlantillaMensajePedidoTest extends TestCase
{
    use RefreshDatabase;

    public function test_render_replaces_sale_variables(): void
    {
        $sale = new Sale([
            'product_name' => 'Mariela',
            'color' => 'Lila',
            'total_amount' => 190,
            'payment_method' => 'yape',
            'customer_data' => ['nombre' => 'Ana'],
        ]);

        $resultado = PlantillaMensajePedido::render(
            'Hola {nombre}, {producto} {color} S/ {total} via {metodo_pago}',
            $sale,
        );

        $this->assertStringContainsString('Ana', $resultado);
        $this->assertStringContainsString('Mariela', $resultado);
        $this->assertStringContainsString('190.00', $resultado);
        $this->assertStringContainsString('yape', $resultado);
    }

    public function test_render_usa_default_si_plantilla_tiene_formato_incorrecto(): void
    {
        $sale = new Sale([
            'product_name' => 'Mariela',
            'color' => 'Lila',
            'total_amount' => 190,
            'payment_method' => 'yape',
            'customer_data' => ['nombre' => 'Ana'],
        ]);

        $resultado = PlantillaMensajePedido::render(
            'listo {"Mariela"}, {"Lila"}, {"190.00"}',
            $sale,
        );

        $this->assertSame('', $resultado);
    }

    public function test_preview_reemplaza_variables_desde_pedido(): void
    {
        $sale = new Sale([
            'product_name' => 'Mariela',
            'color' => 'Lila',
            'total_amount' => 190,
            'customer_data' => ['nombre' => 'Ana'],
        ]);

        $resultado = PlantillaMensajePedido::render(
            'Listo {nombre}, {producto} {color} S/ {total}',
            $sale,
        );

        $this->assertStringContainsString('Ana', $resultado);
        $this->assertStringContainsString('190.00', $resultado);
    }

    public function test_tarjeta_sale_only_confirmable_in_pago_pendiente(): void
    {
        $sale = new Sale([
            'payment_method' => 'tarjeta',
            'status' => SaleStatus::DatosListos,
        ]);

        $this->assertFalse($sale->puedeVerificarPago());

        $sale->status = SaleStatus::PagoPendiente;
        $this->assertTrue($sale->puedeVerificarPago());
    }
}
