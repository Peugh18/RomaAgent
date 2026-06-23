<?php

namespace Tests\Feature;

use App\Models\CompanySetting;
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

        LogIA::query()->create([
            'tipo' => 'request',
            'phone_number' => '51999999999',
            'modelo' => 'gemini-3.1-flash-lite',
        ]);

        $response = $this->actingAs($user)->deleteJson('/api/company-settings');

        $response->assertOk();
        $response->assertJsonPath('empresa.nombre', '');
        $response->assertJsonPath('flujo.saludo_inicial', '');
        $response->assertJsonPath('metodos_pago', []);
        $response->assertJsonPath('configuracion_agente.activado', false);

        $this->assertDatabaseEmpty('logs_ia');
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
