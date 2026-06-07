<?php

namespace Tests\Feature;

use App\Models\CompanySetting;
use App\Models\DeliveryZone;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CompanySettingAlignmentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function configuracionCompleta(): array
    {
        return [
            'company_name' => 'Roma store',
            'celular' => '959166911',
            'email' => 'tienda@example.com',
            'website' => 'https://rome-store.com/',
            'actividad_economica' => 'Moda y Vestuario',
            'tono_bot' => 'cálido y cercano',
            'estilo_comunicacion' => 'natural',
            'moneda' => 'USD',
            'metodos_pago' => [
                ['nombre' => 'Yape', 'descripcion' => 'Pago móvil'],
            ],
            'social_networks' => [
                'instagram' => '@romastore',
                'facebook' => 'facebook.com/romastore',
                'tiktok' => '@romastore',
            ],
            'personalidad_bot' => 'Eres una asesora de ventas amable y profesional. Trato cercano y natural.',
            'respuesta_si_es_bot' => 'Soy asesora de la tienda. Te ayudo por aquí para que sea más rápido.',
            'saludo_inicial' => 'HOLA LINDA WAPA',
            'reglas_comunicacion' => 'Sé amable y directo.',
            'flujo_ventas' => '1. Saluda 2. Cotiza 3. Cierra',
            'horario_entregas' => 'Lunes a sábado 10:00 - 18:00',
        ];
    }

    public function test_prompt_preview_reflects_company_configuration(): void
    {
        $user = User::factory()->create();
        $settings = CompanySetting::factory()->create($this->configuracionCompleta());

        Cache::forget('contexto_prompt_completo_'.$settings->id);

        $response = $this->actingAs($user)->getJson('/api/company-settings');

        $response->assertOk();
        $response->assertJsonMissing(['prompt_completo']);

        $preview = $response->json('prompt_preview');

        $this->assertStringContainsString('Moda y Vestuario', $preview);
        $this->assertStringContainsString('dólares ($)', $preview);
        $this->assertStringContainsString('@romastore', $preview);
        $this->assertStringContainsString('HOLA LINDA WAPA', $preview);
        $this->assertStringContainsString('959166911', $preview);
        $this->assertStringContainsString('asesora de ventas', $preview);
    }

    public function test_statistics_align_with_configuration_state(): void
    {
        $user = User::factory()->create();
        $settings = CompanySetting::factory()->create($this->configuracionCompleta());

        Product::query()->create([
            'name' => 'Polo Roma',
            'price' => 49.90,
            'status' => Product::ESTADO_DISPONIBLE,
        ]);

        Product::query()->create([
            'name' => 'Polo Agotado',
            'price' => 39.90,
            'status' => Product::ESTADO_AGOTADO,
        ]);

        DeliveryZone::query()->create([
            'district' => 'Miraflores',
            'cost_motorizado' => 10,
            'cost_shalom' => 12,
        ]);

        Cache::forget('contexto_prompt_completo_'.$settings->id);

        $response = $this->actingAs($user)->getJson('/api/company-settings');

        $response->assertOk();

        $estadisticas = $response->json('estadisticas');

        $this->assertSame(100, $estadisticas['completitud']);
        $this->assertSame([], $estadisticas['campos_faltantes']);
        $this->assertTrue($estadisticas['esta_lista']);
        $this->assertSame(1, $estadisticas['productos_activos']);
        $this->assertSame(1, $estadisticas['zonas_delivery']);
        $this->assertSame(1, $estadisticas['metodos_pago_count']);
    }

    public function test_update_refreshes_preview_and_statistics(): void
    {
        $user = User::factory()->create();
        $settings = CompanySetting::factory()->create([
            'company_name' => 'Tienda inicial',
            'actividad_economica' => 'Comercio',
            'moneda' => 'PEN',
        ]);

        Cache::forget('contexto_prompt_completo_'.$settings->id);

        $response = $this->actingAs($user)->putJson('/api/company-settings', [
            'company_name' => 'Roma store',
            'actividad_economica' => 'Moda y Vestuario',
            'moneda' => 'USD',
            'social_networks' => [
                'instagram' => '@nueva_cuenta',
                'facebook' => '',
                'tiktok' => '',
            ],
        ]);

        $response->assertOk();

        $preview = $response->json('prompt_preview');

        $this->assertStringContainsString('Moda y Vestuario', $preview);
        $this->assertStringContainsString('dólares ($)', $preview);
        $this->assertStringContainsString('@nueva_cuenta', $preview);
        $this->assertSame('Moda y Vestuario', $response->json('actividad'));
        $this->assertSame('USD', $response->json('moneda'));
        $this->assertSame('@nueva_cuenta', $response->json('empresa.social_networks.instagram'));
    }

    public function test_delivery_zone_rates_appear_in_prompt_from_database(): void
    {
        $user = User::factory()->create();
        $settings = CompanySetting::factory()->create($this->configuracionCompleta());

        DeliveryZone::query()->create([
            'district' => 'Miraflores',
            'cost_motorizado' => 15.50,
            'cost_shalom' => 18.00,
        ]);

        Cache::forget('contexto_prompt_completo_'.$settings->id);

        $response = $this->actingAs($user)->getJson('/api/company-settings');

        $response->assertOk();

        $prompt = $this->promptCompleto($user);

        $this->assertStringContainsString('Miraflores', $prompt);
        $this->assertStringContainsString('15.5', $prompt);
        $this->assertStringContainsString('18', $prompt);
        $this->assertStringContainsString('$ 15.5', $prompt);
    }

    public function test_provincia_shalom_appears_in_prompt_without_motorizado(): void
    {
        $user = User::factory()->create();
        $settings = CompanySetting::factory()->create($this->configuracionCompleta());

        DeliveryZone::query()->create([
            'district' => 'Miraflores',
            'cost_motorizado' => 15,
            'cost_shalom' => 10,
        ]);

        DeliveryZone::query()->create([
            'district' => 'Provincia (Shalom)',
            'cost_motorizado' => 0,
            'cost_shalom' => 12,
        ]);

        Cache::forget('contexto_prompt_completo_'.$settings->id);

        $response = $this->actingAs($user)->getJson('/api/company-settings');

        $response->assertOk();

        $prompt = $this->promptCompleto($user);

        $this->assertStringContainsString('Shalom (Lima y provincia)', $prompt);
        $this->assertStringContainsString('Provincia (Shalom)', $prompt);
        $this->assertStringContainsString('$ 12', $prompt);
        $this->assertStringNotContainsString('Provincia (Shalom): $ 0', $prompt);
    }

    public function test_prompt_preview_is_prefix_of_complete_prompt(): void
    {
        $user = User::factory()->create();
        $settings = CompanySetting::factory()->create($this->configuracionCompleta());

        Cache::forget('contexto_prompt_completo_'.$settings->id);

        $response = $this->actingAs($user)->getJson('/api/company-settings');

        $response->assertOk();

        $preview = $response->json('prompt_preview');
        $completo = $this->promptCompleto($user);

        $this->assertStringStartsWith($preview, $completo);
        $this->assertGreaterThan(strlen($preview), strlen($completo));
    }

    public function test_statistics_warn_when_no_products(): void
    {
        $user = User::factory()->create();
        CompanySetting::factory()->create($this->configuracionCompleta());

        $response = $this->actingAs($user)->getJson('/api/company-settings');

        $response->assertOk();

        $estadisticas = $response->json('estadisticas');

        $this->assertTrue($estadisticas['esta_lista']);
        $this->assertSame(0, $estadisticas['productos_activos']);
        $this->assertContains('No hay productos disponibles en el catálogo', $estadisticas['advertencias']);
    }

    public function test_complete_prompt_includes_all_configured_sections(): void
    {
        $user = User::factory()->create();
        $settings = CompanySetting::factory()->create([
            ...$this->configuracionCompleta(),
            'plantillas_datos' => [
                'motorizado' => ['direccion' => 'Plantilla datos cliente'],
            ],
            'mensaje_recordatorio_datos' => 'Recuerda enviarme tu dirección',
            'protocolo_traspaso' => 'Te derivo con el equipo',
            'formato_registro_venta' => 'Venta: {producto}',
            'horario_shalom' => 'Lunes a viernes',
            'comision_tarjeta' => 3.5,
        ]);

        DeliveryZone::query()->create([
            'district' => 'San Isidro',
            'cost_motorizado' => 12,
            'cost_shalom' => 14,
        ]);

        Cache::forget('contexto_prompt_completo_'.$settings->id);

        $response = $this->actingAs($user)->getJson('/api/company-settings');
        $prompt = $this->promptCompleto($user);

        $this->assertStringContainsString('IDENTIDAD Y PERSONALIDAD', $prompt);
        $this->assertStringContainsString('INFORMACIÓN DE CONTACTO', $prompt);
        $this->assertStringContainsString('SALUDO INICIAL', $prompt);
        $this->assertStringContainsString('REGLAS DE COMUNICACIÓN', $prompt);
        $this->assertStringContainsString('FLUJO DE VENTAS', $prompt);
        $this->assertStringContainsString('Plantilla datos cliente', $prompt);
        $this->assertStringContainsString('Recuerda enviarme tu dirección', $prompt);
        $this->assertStringContainsString('Te derivo con el equipo', $prompt);
        $this->assertStringContainsString('Venta: {producto}', $prompt);
        $this->assertStringContainsString('San Isidro', $prompt);
        $this->assertStringContainsString('Yape', $prompt);
    }

    public function test_personalidad_bot_appears_in_prompt(): void
    {
        $user = User::factory()->create();
        $settings = CompanySetting::factory()->create([
            ...$this->configuracionCompleta(),
            'personalidad_bot' => 'Hablas en femenino y usas palabras cálidas como hermosa.',
            'respuesta_si_es_bot' => 'Soy asesora humana del equipo.',
        ]);

        Cache::forget('contexto_prompt_completo_'.$settings->id);

        $response = $this->actingAs($user)->getJson('/api/company-settings');

        $response->assertOk();
        $prompt = $this->promptCompleto($user);

        $this->assertStringContainsString('Hablas en femenino', $prompt);
        $this->assertStringContainsString('Soy asesora humana del equipo', $prompt);
    }

    public function test_legacy_prompt_fields_are_not_exposed_in_api(): void
    {
        $user = User::factory()->create();
        CompanySetting::factory()->create([
            'saludo_inicial' => 'Hola',
        ]);

        $response = $this->actingAs($user)->getJson('/api/company-settings');

        $response->assertOk();
        $response->assertJsonMissing(['prompt_maestro', 'instrucciones_sistema', 'yape_number']);
    }

    public function test_api_normalizes_empty_plantillas_datos_from_database(): void
    {
        $user = User::factory()->create();
        $settings = CompanySetting::factory()->create([
            ...$this->configuracionCompleta(),
            'plantillas_datos' => [],
        ]);

        Cache::forget('contexto_prompt_completo_'.$settings->id);

        $response = $this->actingAs($user)->getJson('/api/company-settings');

        $response->assertOk();

        $motorizado = $response->json('flujo.plantillas_datos.motorizado');
        $shalom = $response->json('flujo.plantillas_datos.shalom');

        $this->assertNotEmpty($motorizado);
        $this->assertNotEmpty($shalom);
        $this->assertStringContainsString('DNI', implode(' ', $shalom));

        $prompt = $this->promptCompleto($user);
        $this->assertStringContainsString('Para Shalom', $prompt);
        $this->assertStringContainsString('AGENTE VENDEDOR', $prompt);
    }
}
