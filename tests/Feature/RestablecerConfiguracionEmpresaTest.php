<?php

namespace Tests\Feature;

use App\Models\CompanySetting;
use App\Models\DeliveryZone;
use App\Models\LogIA;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestablecerConfiguracionEmpresaTest extends TestCase
{
    use RefreshDatabase;

    public function test_delete_company_settings_resets_configuration(): void
    {
        $user = User::factory()->create();

        CompanySetting::factory()->create([
            'company_name' => 'Roma Store',
            'celular' => '912345678',
            'saludo_inicial' => 'Hola hermosa',
            'metodos_pago' => [['nombre' => 'Yape']],
            'agente_ia_activado' => true,
        ]);

        DeliveryZone::query()->create([
            'district' => 'San Isidro',
            'cost_motorizado' => 15,
            'cost_shalom' => 12,
        ]);

        LogIA::query()->create([
            'tipo' => 'request',
            'phone_number' => '51999999999',
            'modelo' => 'gemini-2.5-flash-lite',
        ]);

        $response = $this->actingAs($user)->deleteJson('/api/company-settings');

        $response->assertOk();
        $response->assertJsonPath('empresa.nombre', '');
        $response->assertJsonPath('flujo.saludo_inicial', '');
        $response->assertJsonPath('metodos_pago', []);
        $response->assertJsonPath('configuracion_agente.activado', false);
        $response->assertJsonPath('estadisticas.zonas_delivery', 0);

        $this->assertDatabaseCount('delivery_zones', 0);
        $this->assertDatabaseCount('logs_ia', 0);
        $this->assertDatabaseHas('company_settings', [
            'company_name' => null,
        ]);
        $this->assertDatabaseHas('mensaje_configs', [
            'saludo_inicial' => null,
        ]);
        $this->assertDatabaseHas('agente_configs', [
            'activado' => false,
        ]);
    }
}
