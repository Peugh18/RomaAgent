<?php

namespace Tests\Feature;

use App\Actions\Pedidos\ActualizarPedidoVenta;
use App\Actions\Pedidos\RegistrarComprobantePedido;
use App\Enums\SaleStatus;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'delivery_cost' => 15,
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
        CompanySetting::factory()->create([
            'mensaje_comprobante_recibido' => 'Recibido hermosa',
        ]);

        $customer = Customer::factory()->create();
        $customer->asignarPedidoActivo(
            \App\Models\Sale::factory()->forProduct(
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
        $sale = \App\Models\Sale::factory()->forProduct($product, $variant)->create([
            'customer_id' => $customer->id,
            'phone_number' => $customer->phone_number,
            'status' => SaleStatus::PagoRecibido,
            'total_amount' => 195,
        ]);

        $this->actingAs($user)->postJson("/api/sales/{$sale->id}/confirm-payment")->assertOk();

        $this->assertDatabaseHas('messages', [
            'phone_number' => $customer->phone_number,
            'direction' => 'outgoing',
        ]);
    }
}
