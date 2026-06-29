<?php

namespace Tests\Feature;

use App\Enums\SaleStatus;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SaleLogisticsTransitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_mark_sale_shipped_with_message(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $customer = Customer::factory()->iaPausada()->create();

        $sale = Sale::factory()->confirmado()->create([
            'customer_id' => $customer->id,
            'phone_number' => $customer->phone_number,
        ]);

        $this->actingAs($user)
            ->postJson("/api/sales/{$sale->id}/mark-shipped", [
                'message' => 'Tu pedido ya salió, {nombre}',
            ])
            ->assertOk()
            ->assertJsonPath('status', SaleStatus::Enviado->value);

        $this->assertNotNull($sale->fresh()->shipped_at);
    }

    public function test_admin_can_mark_sale_shipped_with_image_url(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $customer = Customer::factory()->iaPausada()->create();

        $sale = Sale::factory()->confirmado()->create([
            'customer_id' => $customer->id,
            'phone_number' => $customer->phone_number,
        ]);

        $this->actingAs($user)
            ->postJson("/api/sales/{$sale->id}/mark-shipped", [
                'message' => 'Tu pedido ya salió con Shalom',
                'image_url' => 'https://example.com/boleta-shalom.jpg',
            ])
            ->assertOk()
            ->assertJsonPath('status', SaleStatus::Enviado->value);
    }

    public function test_mark_delivered_reactivates_bot_and_closes_active_sale(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $customer = Customer::factory()->iaPausada()->create([
            'ia_pause_reason' => 'Comprobante de pago por revisar',
        ]);

        $sale = Sale::factory()->create([
            'customer_id' => $customer->id,
            'phone_number' => $customer->phone_number,
            'status' => SaleStatus::Enviado,
        ]);

        $customer->update(['active_sale_id' => $sale->id]);

        $this->actingAs($user)
            ->postJson("/api/sales/{$sale->id}/mark-delivered", [
                'message' => 'Gracias {nombre} por tu compra',
            ])
            ->assertOk()
            ->assertJsonPath('status', SaleStatus::Entregado->value);

        $customer->refresh();
        $this->assertFalse($customer->ia_paused);
        $this->assertNull($customer->active_sale_id);
        $this->assertNotNull($sale->fresh()->delivered_at);
    }

    public function test_transition_preview_returns_rendered_template(): void
    {
        $user = User::factory()->create();

        $sale = Sale::factory()->create([
            'product_name' => 'Vestido Aurora',
            'total_amount' => 140,
            'payment_method' => 'yape',
        ]);

        $this->actingAs($user)
            ->getJson("/api/sales/{$sale->id}/transition-preview?transition=mark_delivered")
            ->assertOk()
            ->assertJsonStructure(['message', 'transition', 'label', 'variables']);
    }

    public function test_pipeline_includes_entregado_in_kanban(): void
    {
        $user = User::factory()->create();

        $entregado = Sale::factory()->create([
            'status' => SaleStatus::Entregado,
            'delivered_at' => now(),
        ]);

        $activo = Sale::factory()->create([
            'status' => SaleStatus::Confirmado,
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/sales?pipeline=1')
            ->assertOk();

        $ids = collect($response->json('sales'))->pluck('id');
        $this->assertContains($activo->id, $ids);
        $this->assertContains($entregado->id, $ids);
    }

    public function test_pipeline_kanban_limits_entregados_to_recent_hours(): void
    {
        $user = User::factory()->create();

        // 10 entregados hoy (dentro de las 24 horas recientes)
        for ($i = 0; $i < 10; $i++) {
            Sale::factory()->create([
                'status' => SaleStatus::Entregado,
                'delivered_at' => now()->subHours($i),
            ]);
        }

        // 5 entregados hace 2 días (más antiguos de 24 horas, archivados)
        for ($i = 0; $i < 5; $i++) {
            Sale::factory()->create([
                'status' => SaleStatus::Entregado,
                'delivered_at' => now()->subDays(2)->subHours($i),
            ]);
        }

        $response = $this->actingAs($user)
            ->getJson('/api/sales?pipeline=1')
            ->assertOk()
            ->assertJsonPath('entregados_total', 15)
            ->assertJsonPath('entregados_archived_count', 5)
            ->assertJsonPath('hours_limit', 24);

        $entregadosInKanban = collect($response->json('sales'))
            ->where('status', SaleStatus::Entregado->value);

        $this->assertCount(10, $entregadosInKanban);
    }

    public function test_pipeline_archive_lists_older_entregados_paginated(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 18; $i++) {
            Sale::factory()->create([
                'status' => SaleStatus::Entregado,
                'delivered_at' => now()->subDays($i),
            ]);
        }

        $this->actingAs($user)
            ->getJson('/api/sales?pipeline=1&scope=archive')
            ->assertOk()
            ->assertJsonPath('total', 18)
            ->assertJsonStructure(['data', 'current_page', 'last_page', 'total']);
    }

    public function test_pipeline_history_lists_entregado_paginated(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 16; $i++) {
            Sale::factory()->create([
                'status' => SaleStatus::Entregado,
                'delivered_at' => now()->subDays($i),
            ]);
        }

        $this->actingAs($user)
            ->getJson('/api/sales?pipeline=1&scope=history')
            ->assertOk()
            ->assertJsonPath('total', 16)
            ->assertJsonStructure(['data', 'current_page', 'last_page', 'total']);
    }

    public function test_can_revert_delivered_to_shipped(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create(['ia_paused' => false]);

        $sale = Sale::factory()->create([
            'customer_id' => $customer->id,
            'status' => SaleStatus::Entregado,
            'delivered_at' => now(),
        ]);

        $this->actingAs($user)
            ->postJson("/api/sales/{$sale->id}/revert-delivered")
            ->assertOk()
            ->assertJsonPath('status', SaleStatus::Enviado->value);

        $customer->refresh();
        $this->assertTrue($customer->ia_paused);
    }
}
