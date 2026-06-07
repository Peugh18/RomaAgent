<?php

namespace Tests\Feature;

use App\Models\AgenteConfig;
use App\Models\CompanySetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfiguracionAgenteApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_agent_config_maps_to_frontend_shape_on_load(): void
    {
        $user = User::factory()->create();

        CompanySetting::factory()->create([
            'agente_ia_activado' => true,
            'agente_ia_modelo' => 'gemini-2.5-pro',
            'agente_ia_temperatura' => 0.5,
        ]);

        $response = $this->actingAs($user)->getJson('/api/company-settings');

        $response->assertOk();
        $response->assertJsonPath('configuracion_agente.activado', true);
        $response->assertJsonPath('configuracion_agente.modelo', 'gemini-2.5-pro');
    }

    public function test_saving_other_fields_does_not_disable_agent_when_active(): void
    {
        $user = User::factory()->create();

        CompanySetting::factory()->create([
            'company_name' => 'Roma Store',
            'agente_ia_activado' => true,
            'agente_ia_modelo' => 'gemini-2.5-flash',
        ]);

        $response = $this->actingAs($user)->putJson('/api/company-settings', [
            'company_name' => 'Roma Store Actualizado',
            'agente_ia_activado' => true,
            'agente_ia_modelo' => 'gemini-2.5-flash',
            'agente_ia_temperatura' => 0.7,
        ]);

        $response->assertOk();

        $this->assertTrue(AgenteConfig::query()->value('activado'));
    }

    public function test_api_exposes_standard_size(): void
    {
        $user = User::factory()->create();

        CompanySetting::factory()->create([
            'standard_size' => 'M',
        ]);

        $response = $this->actingAs($user)->getJson('/api/company-settings');

        $response->assertOk();
        $response->assertJsonPath('standard_size', 'M');
        $response->assertJsonPath('empresa.standard_size', 'M');
    }
}
