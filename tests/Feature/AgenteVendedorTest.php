<?php

namespace Tests\Feature;

use App\Actions\Pedidos\ActualizarPedidoVenta;
use App\Actions\Pedidos\RegistrarComprobantePedido;
use App\Enums\SaleStatus;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Message;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\User;
use App\Services\Agente\EjecutorHerramientasAgente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AgenteVendedorTest extends TestCase
{
    use RefreshDatabase;

    public function test_actualizar_pedido_links_product_and_customer(): void
    {
        $product = Product::query()->create([
            'name' => 'Mariela',
            'price' => 180,
            'status' => Product::ESTADO_DISPONIBLE,
        ]);

        ProductVariant::query()->create([
            'product_id' => $product->id,
            'color' => 'Lila',
            'sizes_stock' => ['UNICA' => 10],
        ]);

        $customer = Customer::factory()->create(['phone_number' => '+51988776655']);

        $sale = app(ActualizarPedidoVenta::class)->handle($customer, [
            'product_name' => 'Mariela',
            'color' => 'Lila',
            'unit_price' => 180,
            'total_amount' => 195,
            'status' => 'pago_pendiente',
            'payment_method' => 'yape',
        ]);

        $this->assertSame('Mariela', $sale->product_name);
        $this->assertSame(SaleStatus::PagoPendiente, $sale->status);
        $this->assertSame($product->id, $sale->product_id);

        $customer->refresh();
        $this->assertSame($sale->id, $customer->active_sale_id);
    }

    public function test_registrar_comprobante_pausa_ia(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-05 14:00:00', 'America/Lima'));

        CompanySetting::factory()->create([
            'mensaje_comprobante_recibido' => 'Recibido hermosa',
            'horario_atencion' => '9am - 10pm',
        ]);

        $customer = Customer::factory()->create();
        $customer->asignarPedidoActivo(
            Sale::factory()->forProduct(
                Product::query()->create(['name' => 'Aurora', 'price' => 150, 'status' => Product::ESTADO_DISPONIBLE])
            )->create(['customer_id' => $customer->id, 'phone_number' => $customer->phone_number])
        );

        $result = app(RegistrarComprobantePedido::class)->handle($customer->fresh());

        $this->assertStringContainsString('Recibido', $result['mensaje']);
        $customer->refresh();
        $this->assertTrue($customer->ia_paused);
    }

    public function test_confirm_payment_sends_whatsapp_message(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        CompanySetting::factory()->create([
            'mensaje_pedido_confirmado' => 'Confirmado {producto} por S/ {total}',
        ]);

        $product = Product::query()->create([
            'name' => 'Mariela',
            'price' => 180,
            'status' => Product::ESTADO_DISPONIBLE,
        ]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'color' => 'Lila',
            'sizes_stock' => ['UNICA' => 3],
        ]);

        $customer = Customer::factory()->iaPausada()->create();
        $sale = Sale::factory()->forProduct($product, $variant)->create([
            'customer_id' => $customer->id,
            'phone_number' => $customer->phone_number,
            'status' => SaleStatus::PagoRecibido,
            'total_amount' => 195,
        ]);

        $this->actingAs($user)->postJson("/api/sales/{$sale->id}/confirm-payment", [
            'message' => 'Confirmado {producto} por S/ {total}',
        ])->assertOk();

        $this->assertDatabaseHas('messages', [
            'phone_number' => $customer->phone_number,
            'direction' => 'outgoing',
        ]);
    }

    public function test_backend_validation_blocks_commercial_tools_on_comprobante(): void
    {
        $customer = Customer::factory()->create(['phone_number' => '+51988776655']);
        $mensaje = Message::factory()->incoming()->create([
            'phone_number' => $customer->phone_number,
            'metadata' => [
                'type' => 'image',
                'vision' => [
                    'inbound_profile' => [
                        'tipo_mensaje' => 'comprobante',
                    ],
                ],
            ],
        ]);

        $ejecutor = app(EjecutorHerramientasAgente::class);

        // 1. Trying to run enviar_foto_producto should be blocked
        $res = $ejecutor->ejecutar('enviar_foto_producto', [
            'product_name' => 'Mariela',
            'color' => 'Lila',
        ], $customer, $mensaje);

        $this->assertFalse($res['ok']);
        $this->assertStringContainsString('PROHIBIDO recomendar', $res['error']);

        // 2. Trying to run buscar_productos should be blocked
        $res2 = $ejecutor->ejecutar('buscar_productos', [
            'query' => 'lila',
        ], $customer, $mensaje);

        $this->assertFalse($res2['ok']);
        $this->assertStringContainsString('PROHIBIDO recomendar', $res2['error']);
    }

    public function test_actualizar_pedido_fails_if_color_not_available(): void
    {
        $product = Product::query()->create([
            'name' => 'Mariela',
            'price' => 180,
            'status' => Product::ESTADO_DISPONIBLE,
        ]);

        ProductVariant::query()->create([
            'product_id' => $product->id,
            'color' => 'Lila',
            'sizes_stock' => ['UNICA' => 10],
        ]);

        $customer = Customer::factory()->create();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("El color 'Verde' no está disponible para el producto 'Mariela'. Colores disponibles: Lila.");

        app(ActualizarPedidoVenta::class)->handle($customer, [
            'product_name' => 'Mariela',
            'color' => 'Verde',
            'unit_price' => 180,
            'quantity' => 1,
        ]);
    }

    public function test_actualizar_pedido_fails_if_size_out_of_stock(): void
    {
        $product = Product::query()->create([
            'name' => 'Mariela',
            'price' => 180,
            'status' => Product::ESTADO_DISPONIBLE,
        ]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'color' => 'Lila',
            'sizes_stock' => ['S' => 2, 'L' => 3],
        ]);

        $customer = Customer::factory()->create();

        // 1. Asking for size 'M' which does not exist (and variant has multiple sizes, so it is not forced to UNICA)
        try {
            app(ActualizarPedidoVenta::class)->handle($customer, [
                'product_name' => 'Mariela',
                'color' => 'Lila',
                'size' => 'M',
                'quantity' => 1,
            ]);
            $this->fail('Debería haber lanzado una excepción por talla no disponible');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString("La talla 'M' no está disponible para 'Mariela' en color 'Lila'", $e->getMessage());
        }

        // Set the variant back to default UNICA stock to test quantity validation
        $variant->update(['sizes_stock' => ['UNICA' => 2]]);

        // 2. Asking for quantity greater than stock
        try {
            app(ActualizarPedidoVenta::class)->handle($customer, [
                'product_name' => 'Mariela',
                'color' => 'Lila',
                'size' => 'UNICA',
                'quantity' => 5,
            ]);
            $this->fail('Debería haber lanzado una excepción por exceso de cantidad');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString("No hay suficiente stock para 'Mariela' en color 'Lila' y talla 'UNICA'", $e->getMessage());
        }
    }
}
