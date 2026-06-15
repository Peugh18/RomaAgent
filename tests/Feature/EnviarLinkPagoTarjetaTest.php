<?php

namespace Tests\Feature;

use App\Enums\SaleStatus;
use App\Models\CompanySetting;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EnviarLinkPagoTarjetaTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_puede_enviar_solo_link_de_pago_tarjeta(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $settings = CompanySetting::factory()->create();

        $settings->obtenerOCrearVentas()->update([
            'link_pago_tarjeta' => 'https://pay.example.com/checkout?amount={total}&id={sale_id}',
        ]);

        $sale = Sale::factory()->create([
            'status' => SaleStatus::PagoPendiente,
            'payment_method' => 'tarjeta',
            'total_amount' => 190,
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/sales/{$sale->id}/send-payment-link", [
                'link' => 'https://pay.example.com/checkout?amount=190.00&id='.$sale->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('link', 'https://pay.example.com/checkout?amount=190.00&id='.$sale->id);
    }

    public function test_no_envia_link_si_no_es_tarjeta_pendiente(): void
    {
        $user = User::factory()->create();
        CompanySetting::factory()->create();

        $sale = Sale::factory()->create([
            'status' => SaleStatus::PagoRecibido,
            'payment_method' => 'yape',
        ]);

        $this->actingAs($user)
            ->postJson("/api/sales/{$sale->id}/send-payment-link")
            ->assertStatus(422);
    }
}
