<?php

namespace Tests\Unit;

use App\Models\Message;
use App\Models\Sale;
use App\Support\ComprobantePagoMensaje;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ComprobantePagoMensajeTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolver_prefers_tagged_comprobante_over_newer_chat_image(): void
    {
        $sale = Sale::factory()->create([
            'phone_number' => '51999111222',
            'payment_received_at' => now()->subHour(),
        ]);

        $tagged = Message::factory()->incoming()->create([
            'phone_number' => $sale->phone_number,
            'metadata' => [
                'type' => 'image',
                'image_url' => 'https://example.com/comprobante.jpg',
                'is_payment_comprobante' => true,
                'sale_id' => $sale->id,
            ],
        ]);

        Message::factory()->incoming()->create([
            'phone_number' => $sale->phone_number,
            'metadata' => [
                'type' => 'image',
                'image_url' => 'https://example.com/otra-foto.jpg',
            ],
        ]);

        $url = ComprobantePagoMensaje::resolverParaPedido(
            $sale,
            Collection::make(),
            Collection::make([$sale->id => Collection::make([$tagged])]),
            Collection::make(),
        );

        $this->assertSame('https://example.com/comprobante.jpg', $url);
    }

    public function test_marcar_links_message_and_sale_metadata(): void
    {
        $sale = Sale::factory()->create();
        $mensaje = Message::factory()->incoming()->create([
            'phone_number' => $sale->phone_number,
            'metadata' => ['type' => 'image', 'image_url' => 'https://example.com/yape.jpg'],
        ]);

        ComprobantePagoMensaje::marcar($mensaje, $sale);

        $mensaje->refresh();
        $sale->refresh();

        $this->assertTrue($mensaje->metadata['is_payment_comprobante'] ?? false);
        $this->assertSame($sale->id, $mensaje->metadata['sale_id'] ?? null);
        $this->assertSame($mensaje->id, $sale->agent_metadata['comprobante_message_id'] ?? null);
    }
}
