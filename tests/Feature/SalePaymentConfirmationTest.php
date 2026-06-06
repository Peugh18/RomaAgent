<?php

namespace Tests\Feature;

use App\Enums\SaleStatus;
use App\Models\Customer;
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
        ]);

        $customer->update(['active_sale_id' => $sale->id]);

        $response = $this->actingAs($user)->postJson("/api/sales/{$sale->id}/confirm-payment");

        $response->assertOk();
        $response->assertJsonPath('status', SaleStatus::Confirmado->value);

        $variant->refresh();
        $this->assertSame(4, $variant->sizes_stock['UNICA']);

        $customer->refresh();
        $this->assertFalse($customer->ia_paused);
        $this->assertNull($customer->active_sale_id);

        $this->assertDatabaseHas('sales', [
            'id' => $sale->id,
            'status' => SaleStatus::Confirmado->value,
            'confirmed_by_user_id' => $user->id,
        ]);
    }

    public function test_cannot_confirm_sale_that_is_already_confirmed(): void
    {
        $user = User::factory()->create();
        $sale = Sale::factory()->confirmado()->create();

        $this->actingAs($user)
            ->postJson("/api/sales/{$sale->id}/confirm-payment")
            ->assertStatus(422);
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

        $agente = app(\App\Actions\GenerarRespuestaAgente::class);

        $message = \App\Models\Message::factory()->create([
            'phone_number' => '+51911112222',
            'direction' => 'incoming',
            'metadata' => ['type' => 'text'],
        ]);

        $this->assertFalse($agente->debeResponder($message));
    }
}
