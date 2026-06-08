<?php

namespace Tests\Feature;

use App\Enums\SaleStatus;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get('/dashboard');
        $response->assertRedirect('/login');
    }

    public function test_authenticated_users_can_visit_the_dashboard(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/dashboard');
        $response->assertStatus(200);
    }

    public function test_dashboard_includes_entregado_sales_in_ventas_hoy_and_recientes(): void
    {
        $user = User::factory()->create();

        Sale::factory()->create([
            'status' => SaleStatus::Entregado,
            'confirmed_at' => now(),
            'delivered_at' => now(),
            'total_amount' => 190,
            'product_name' => 'Mariela',
        ]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('stats.ventas_hoy', 190)
                ->where('stats.pedidos_activos', 0)
                ->has('pipelineOverview', 1)
                ->where('pipelineOverview.0.status', SaleStatus::Entregado->value)
                ->has('pedidosRecientes', 1)
                ->where('pedidosRecientes.0.status', SaleStatus::Entregado->value)
            );
    }

    public function test_dashboard_includes_pipeline_overview_and_trend_stats(): void
    {
        $user = User::factory()->create();

        Sale::factory()->create([
            'status' => SaleStatus::Cotizando,
            'total_amount' => 120,
        ]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('stats.pedidos_activos', 1)
                ->has('stats.ventas_trend')
                ->has('pipelineOverview', 1)
                ->where('pipelineOverview.0.status', SaleStatus::Cotizando->value)
            );
    }
}
