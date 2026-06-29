<?php

namespace Tests\Feature;

use App\Enums\SaleStatus;
use App\Enums\UserRole;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RolePermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_user_can_access_all_routes_and_see_money_on_dashboard(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        Sale::factory()->create([
            'status' => SaleStatus::Entregado,
            'confirmed_at' => now(),
            'total_amount' => 190.0,
        ]);

        $this->actingAs($admin);

        // Can access dashboard with money
        $this->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('stats.ventas_hoy', 190)
                ->where('pedidosRecientes.0.total_amount', 190)
                ->has('chartData', 1)
            );

        // Can access clients and company settings
        $this->get('/clientes')->assertOk();
        $this->get('/api/customers')->assertOk();
        $this->get('/configuracion/empresa')->assertOk();
        $this->get('/api/company-settings')->assertOk();
    }

    public function test_worker_user_cannot_see_money_on_dashboard_and_is_blocked_from_restricted_routes(): void
    {
        $trabajador = User::factory()->create([
            'role' => UserRole::Trabajador,
        ]);

        Sale::factory()->create([
            'status' => SaleStatus::Entregado,
            'confirmed_at' => now(),
            'total_amount' => 190,
        ]);

        $this->actingAs($trabajador);

        // Can access dashboard BUT money stats must be zero or empty
        $this->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('stats.ventas_hoy', 0)
                ->where('stats.ventas_ayer', 0)
                ->where('stats.ventas_mes', 0)
                ->where('stats.ventas_trend', null)
                ->where('pedidosRecientes.0.total_amount', 0)
                ->where('chartData', [])
            );

        // Blocked from web clients and settings (redirected to dashboard)
        $this->get('/clientes')
            ->assertRedirect(route('dashboard'));

        $this->get('/configuracion/empresa')
            ->assertRedirect(route('dashboard'));

        // Blocked from API clients and settings (returns 403)
        $this->getJson('/api/customers')
            ->assertStatus(403);

        $this->getJson('/api/company-settings')
            ->assertStatus(403);

        // Allowed to access chat and other pipeline / catalog pages
        $this->get('/chat')->assertOk();
        $this->get('/pipeline')->assertOk();
        $this->get('/productos')->assertOk();
        $this->get('/categorias')->assertOk();
        $this->get('/configuracion/zonas-envio')->assertOk();
    }
}
