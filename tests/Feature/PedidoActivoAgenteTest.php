<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Message;
use App\Models\Sale;
use App\Services\Agente\EjecutorHerramientasAgente;
use App\Services\ContextoConversacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PedidoActivoAgenteTest extends TestCase
{
    use RefreshDatabase;

    public function test_consultar_pedido_activo_incluye_cantidad_y_desglose(): void
    {
        $customer = Customer::factory()->create();
        $sale = Sale::factory()->create([
            'customer_id' => $customer->id,
            'phone_number' => $customer->phone_number,
            'product_name' => 'Mariela',
            'color' => 'Azul',
            'size' => 'UNICA',
            'quantity' => 3,
            'unit_price' => 180,
            'delivery_cost' => 0,
            'total_amount' => 540,
        ]);
        $customer->asignarPedidoActivo($sale);

        $resultado = app(EjecutorHerramientasAgente::class)->ejecutar(
            'consultar_pedido_activo',
            [],
            $customer->fresh(),
            Message::factory()->create(['phone_number' => $customer->phone_number]),
        );

        $this->assertTrue($resultado['ok']);
        $this->assertSame(3, $resultado['pedido']['quantity']);
        $this->assertSame(540.0, $resultado['pedido']['total_amount']);
        $this->assertSame('talla estándar', $resultado['pedido']['size']);
        $this->assertStringContainsString('3 × S/ 180.00', $resultado['pedido']['desglose']);
    }

    public function test_prompt_agente_incluye_bloque_pedido_activo(): void
    {
        $customer = Customer::factory()->create();
        $sale = Sale::factory()->create([
            'customer_id' => $customer->id,
            'phone_number' => $customer->phone_number,
            'product_name' => 'Mariela azul',
            'quantity' => 3,
            'unit_price' => 180,
            'total_amount' => 540,
        ]);
        $customer->asignarPedidoActivo($sale);

        $prompt = app(ContextoConversacion::class)->construirPromptParaAgenteConPedido($customer->fresh());

        $this->assertStringContainsString('PEDIDO ACTIVO (FUENTE DE VERDAD', $prompt);
        $this->assertStringContainsString('Cantidad: 3 unidad(es)', $prompt);
        $this->assertStringContainsString('TOTAL A COBRAR: S/ 540.00', $prompt);
    }
}
