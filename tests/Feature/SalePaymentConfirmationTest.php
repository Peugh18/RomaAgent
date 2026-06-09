<?php

namespace Tests\Feature;

use App\Actions\GenerarRespuestaAgente;
use App\Enums\SaleStatus;
use App\Models\Customer;
use App\Models\Message;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SalePaymentConfirmationTest extends TestCase
{
    use RefreshDatabase;

    private function mensajeConfirmacion(): string
    {
        return 'Confirmado test {producto} por S/ {total}';
    }

    public function test_admin_can_confirm_payment_and_stock_decreases(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $product = Product::query()->create([
            'name' => 'Mariela',
            'price' => 180,
            'status' => Product::ESTADO_DISPONIBLE,
        ]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'color' => 'Lila',
            'sizes_stock' => ['UNICA' => 5],
        ]);

        $customer = Customer::factory()->iaPausada()->create([
            'phone_number' => '+51912345678',
        ]);

        $sale = Sale::factory()->forProduct($product, $variant)->create([
            'customer_id' => $customer->id,
            'phone_number' => $customer->phone_number,
            'status' => SaleStatus::PagoRecibido,
            'size' => 'UNICA',
            'quantity' => 1,
            'total_amount' => 195,
            'delivery_cost' => 15,
            'payment_method' => 'yape',
        ]);

        $customer->update(['active_sale_id' => $sale->id]);

        $response = $this->actingAs($user)->postJson("/api/sales/{$sale->id}/confirm-payment", [
            'message' => $this->mensajeConfirmacion(),
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', SaleStatus::Confirmado->value);

        $variant->refresh();
        $this->assertSame(4, $variant->sizes_stock['UNICA']);

        $customer->refresh();
        $this->assertTrue($customer->ia_paused);

        $this->assertDatabaseHas('sales', [
            'id' => $sale->id,
            'status' => SaleStatus::Confirmado->value,
            'confirmed_by_user_id' => $user->id,
        ]);
    }

    public function test_cannot_confirm_yape_from_datos_listos_without_comprobante(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $sale = Sale::factory()->create([
            'status' => SaleStatus::DatosListos,
            'payment_method' => 'yape',
        ]);

        $this->actingAs($user)
            ->postJson("/api/sales/{$sale->id}/confirm-payment", [
                'message' => $this->mensajeConfirmacion(),
            ])
            ->assertStatus(422);
    }

    public function test_can_confirm_yape_after_comprobante_registered(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $product = Product::query()->create([
            'name' => 'Mariela',
            'price' => 180,
            'status' => Product::ESTADO_DISPONIBLE,
        ]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'color' => 'camel',
            'sizes_stock' => ['UNICA' => 3],
        ]);

        $sale = Sale::factory()->forProduct($product, $variant)->create([
            'status' => SaleStatus::PagoRecibido,
            'payment_method' => 'yape',
            'payment_received_at' => now(),
            'size' => 'UNICA',
            'quantity' => 1,
            'total_amount' => 192,
            'delivery_cost' => 12,
        ]);

        $this->actingAs($user)
            ->postJson("/api/sales/{$sale->id}/confirm-payment", [
                'message' => $this->mensajeConfirmacion(),
            ])
            ->assertOk()
            ->assertJsonPath('status', SaleStatus::Confirmado->value);
    }

    public function test_tarjeta_only_confirmable_from_pago_pendiente(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $saleDatosListos = Sale::factory()->create([
            'status' => SaleStatus::DatosListos,
            'payment_method' => 'tarjeta',
        ]);

        $this->actingAs($user)
            ->postJson("/api/sales/{$saleDatosListos->id}/confirm-payment", [
                'message' => 'Confirmado tarjeta',
            ])
            ->assertStatus(422);

        $salePendiente = Sale::factory()->create([
            'status' => SaleStatus::PagoPendiente,
            'payment_method' => 'tarjeta',
        ]);

        $this->actingAs($user)
            ->postJson("/api/sales/{$salePendiente->id}/confirm-payment", [
                'message' => 'Confirmado tarjeta',
            ])
            ->assertOk()
            ->assertJsonPath('status', SaleStatus::Confirmado->value);
    }

    public function test_cannot_confirm_sale_that_is_already_confirmed(): void
    {
        $user = User::factory()->create();
        $sale = Sale::factory()->confirmado()->create();

        $this->actingAs($user)
            ->postJson("/api/sales/{$sale->id}/confirm-payment", [
                'message' => 'Otro mensaje',
            ])
            ->assertStatus(422);
    }

    public function test_confirm_payment_auto_pauses_ia(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $product = Product::query()->create([
            'name' => 'Mariela',
            'price' => 180,
            'status' => Product::ESTADO_DISPONIBLE,
        ]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'color' => 'Lila',
            'sizes_stock' => ['UNICA' => 5],
        ]);

        $customer = Customer::factory()->create([
            'phone_number' => '+51912345678',
            'ia_paused' => false,
        ]);

        $sale = Sale::factory()->forProduct($product, $variant)->create([
            'customer_id' => $customer->id,
            'phone_number' => $customer->phone_number,
            'status' => SaleStatus::PagoRecibido,
            'size' => 'UNICA',
            'quantity' => 1,
            'total_amount' => 195,
            'delivery_cost' => 15,
            'payment_method' => 'yape',
        ]);

        $customer->update(['active_sale_id' => $sale->id]);

        $this->assertFalse($customer->fresh()->ia_paused);

        $response = $this->actingAs($user)->postJson("/api/sales/{$sale->id}/confirm-payment", [
            'message' => $this->mensajeConfirmacion(),
        ]);

        $response->assertOk();

        $customer->refresh();
        $this->assertTrue($customer->ia_paused);
        $this->assertSame('Pedido confirmado: modo humano activo', $customer->ia_pause_reason);
    }

    public function test_active_sale_endpoint_returns_open_sale_for_phone(): void
    {
        $user = User::factory()->create();
        $sale = Sale::factory()->pagoRecibido()->create([
            'phone_number' => '+51999888777',
        ]);

        $this->actingAs($user)
            ->getJson('/api/sales/active/+51999888777')
            ->assertOk()
            ->assertJsonPath('id', $sale->id);
    }

    public function test_ia_does_not_respond_when_customer_is_paused(): void
    {
        Customer::factory()->iaPausada()->create([
            'phone_number' => '+51911112222',
        ]);

        $agente = app(GenerarRespuestaAgente::class);

        $message = Message::factory()->create([
            'phone_number' => '+51911112222',
            'direction' => 'incoming',
            'metadata' => ['type' => 'text'],
        ]);

        $this->assertFalse($agente->debeResponder($message));
    }

    public function test_cannot_send_manual_message_when_bot_is_active(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        Customer::factory()->create([
            'phone_number' => '+51955556666',
            'ia_paused' => false,
        ]);

        $this->actingAs($user)
            ->postJson('/api/send-message', [
                'phone_number' => '+51955556666',
                'content' => 'Hola manual',
            ])
            ->assertStatus(422);
    }

    public function test_can_send_manual_message_when_human_mode(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        Customer::factory()->iaPausada()->create([
            'phone_number' => '+51955557777',
        ]);

        $this->actingAs($user)
            ->postJson('/api/send-message', [
                'phone_number' => '+51955557777',
                'content' => 'Link de pago aquí',
            ])
            ->assertOk();
    }

    public function test_admin_can_cancel_sale_and_ia_resumes(): void
    {
        $user = User::factory()->create();

        $customer = Customer::factory()->iaPausada()->create([
            'phone_number' => '+51912345678',
        ]);

        $sale = Sale::factory()->confirmado()->create([
            'customer_id' => $customer->id,
            'phone_number' => $customer->phone_number,
        ]);

        $customer->update(['active_sale_id' => $sale->id]);

        $this->assertTrue($customer->fresh()->ia_paused);

        $response = $this->actingAs($user)->postJson("/api/sales/{$sale->id}/cancel");

        $response->assertOk();
        $response->assertJsonPath('status', SaleStatus::Cancelado->value);

        $sale->refresh();
        $this->assertSame(SaleStatus::Cancelado, $sale->status);

        $customer->refresh();
        $this->assertFalse($customer->ia_paused);
        $this->assertNull($customer->active_sale_id);
    }

    public function test_cannot_cancel_delivered_sale(): void
    {
        $user = User::factory()->create();
        $sale = Sale::factory()->entregado()->create();

        $this->actingAs($user)
            ->postJson("/api/sales/{$sale->id}/cancel")
            ->assertStatus(422);
    }

    public function test_cannot_cancel_already_cancelled_sale(): void
    {
        $user = User::factory()->create();
        $sale = Sale::factory()->create([
            'status' => SaleStatus::Cancelado,
        ]);

        $this->actingAs($user)
            ->postJson("/api/sales/{$sale->id}/cancel")
            ->assertStatus(422);
    }
}
