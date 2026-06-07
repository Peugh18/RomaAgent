<?php

namespace Tests\Feature;

use App\Actions\Pedidos\RegistrarComprobantePedido;
use App\Enums\SaleStatus;
use App\Models\Customer;
use App\Models\Message;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PipelineComprobanteImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_pipeline_uses_tagged_payment_comprobante_not_latest_chat_image(): void
    {
        $user = User::factory()->create();
        $phone = '51999111222';
        $customer = Customer::factory()->create(['phone_number' => $phone]);

        $sale = Sale::factory()->create([
            'customer_id' => $customer->id,
            'phone_number' => $phone,
            'status' => SaleStatus::PagoRecibido,
            'payment_received_at' => now()->subHour(),
            'agent_metadata' => ['comprobante_message_id' => null],
        ]);

        Message::factory()->incoming()->create([
            'phone_number' => $phone,
            'created_at' => now()->subHour(),
            'metadata' => [
                'type' => 'image',
                'image_url' => 'https://example.com/comprobante-yape.jpg',
                'is_payment_comprobante' => true,
                'sale_id' => $sale->id,
            ],
        ]);

        Message::factory()->incoming()->create([
            'phone_number' => $phone,
            'created_at' => now(),
            'metadata' => [
                'type' => 'image',
                'image_url' => 'https://example.com/foto-producto-reciente.jpg',
            ],
        ]);

        $this->actingAs($user)
            ->getJson('/api/sales?pipeline=1')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $sale->id,
                'comprobante_url' => 'https://example.com/comprobante-yape.jpg',
            ])
            ->assertJsonMissing([
                'comprobante_url' => 'https://example.com/foto-producto-reciente.jpg',
            ]);
    }

    public function test_pipeline_falls_back_to_image_before_payment_received_at(): void
    {
        $user = User::factory()->create();
        $phone = '51988333444';
        $paymentAt = now()->subMinutes(30);

        $sale = Sale::factory()->create([
            'phone_number' => $phone,
            'status' => SaleStatus::PagoRecibido,
            'payment_received_at' => $paymentAt,
        ]);

        Message::factory()->incoming()->create([
            'phone_number' => $phone,
            'created_at' => $paymentAt->copy()->subMinute(),
            'metadata' => [
                'type' => 'image',
                'image_url' => 'https://example.com/voucher-pago.jpg',
            ],
        ]);

        Message::factory()->incoming()->create([
            'phone_number' => $phone,
            'created_at' => now(),
            'metadata' => [
                'type' => 'image',
                'image_url' => 'https://example.com/otra-imagen-despues.jpg',
            ],
        ]);

        $this->actingAs($user)
            ->getJson('/api/sales?pipeline=1')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $sale->id,
                'comprobante_url' => 'https://example.com/voucher-pago.jpg',
            ]);
    }

    public function test_registrar_comprobante_tags_incoming_image_message(): void
    {
        $customer = Customer::factory()->create();
        $sale = Sale::factory()->forProduct(
            Product::query()->create(['name' => 'Aurora', 'price' => 150, 'status' => Product::ESTADO_DISPONIBLE])
        )->create([
            'customer_id' => $customer->id,
            'phone_number' => $customer->phone_number,
            'status' => SaleStatus::DatosListos,
        ]);

        $customer->asignarPedidoActivo($sale);

        $mensaje = Message::factory()->incoming()->create([
            'phone_number' => $customer->phone_number,
            'metadata' => [
                'type' => 'image',
                'image_url' => 'https://example.com/yape.jpg',
            ],
        ]);

        app(RegistrarComprobantePedido::class)->handle($customer->fresh(), $mensaje);

        $mensaje->refresh();
        $sale->refresh();

        $this->assertTrue($mensaje->metadata['is_payment_comprobante'] ?? false);
        $this->assertSame($sale->id, $mensaje->metadata['sale_id'] ?? null);
        $this->assertSame($mensaje->id, $sale->agent_metadata['comprobante_message_id'] ?? null);
    }
}
