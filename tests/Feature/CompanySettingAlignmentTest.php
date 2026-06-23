<?php

namespace Tests\Feature;

use App\Models\CompanySetting;
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

    public function test_prompt_completo_reflects_company_configuration(): void
    {
        $user = User::factory()->create();
        $settings = CompanySetting::factory()->create($this->configuracionCompleta());

        Cache::forget('contexto_prompt_completo_'.$settings->id);

        $response = $this->actingAs($user)->getJson('/api/company-settings');

        $response->assertOk();
        $response->assertJsonMissing(['prompt_completo']);

        $prompt = $this->promptCompleto($user);

        $this->assertStringContainsString('Moda y Vestuario', $prompt);
        $this->assertStringContainsString('soles peruanos', $prompt);
        $this->assertStringContainsString('@romastore', $prompt);
        $this->assertStringContainsString('HOLA LINDA WAPA', $prompt);
        $this->assertStringContainsString('asesora de ventas', $prompt);
        $this->assertStringContainsString('buscar_productos', $prompt);
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
        Cache::forget('contexto_prompt_completo_'.$settings->id);

        $response = $this->actingAs($user)->getJson('/api/company-settings');

        $response->assertOk();

        $estadisticas = $response->json('estadisticas');

        $this->assertSame(100, $estadisticas['completitud']);
        $this->assertSame([], $estadisticas['campos_faltantes']);
        $this->assertTrue($estadisticas['esta_lista']);
        $this->assertSame(1, $estadisticas['productos_activos']);
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
                'website' => 'https://new-website.com',
            ],
        ]);

        $response->assertOk();

        $prompt = $this->promptCompleto($user);

        $this->assertStringContainsString('Moda y Vestuario', $prompt);
        $this->assertStringContainsString('soles peruanos', $prompt);
        $this->assertStringContainsString('@nueva_cuenta', $prompt);
        $this->assertStringContainsString('https://new-website.com', $prompt);
        $this->assertSame('Moda y Vestuario', $response->json('actividad'));
        $this->assertSame('USD', $response->json('moneda'));
        $this->assertSame('@nueva_cuenta', $response->json('empresa.social_networks.instagram'));
        $this->assertSame('https://new-website.com', $response->json('empresa.social_networks.website'));
    }

    public function test_prompt_completo_endpoint_matches_contexto_conversacion(): void
    {
        $user = User::factory()->create();
        $settings = CompanySetting::factory()->create($this->configuracionCompleta());

        Cache::forget('contexto_prompt_completo_'.$settings->id);

        $prompt = $this->promptCompleto($user);

        $this->assertStringContainsString('AGENTE VENDEDOR', $prompt);
        $this->assertGreaterThan(500, strlen($prompt));
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
            'mensaje_recordatorio_datos' => 'Recuerda enviarme tu dirección',
            'protocolo_traspaso' => 'Te derivo con el equipo',
            'formato_registro_venta' => 'Venta: {producto}',
            'comision_tarjeta' => 3.5,
        ]);

        Cache::forget('contexto_prompt_completo_'.$settings->id);

        $response = $this->actingAs($user)->getJson('/api/company-settings');
        $prompt = $this->promptCompleto($user);

        $this->assertStringContainsString('IDENTIDAD Y PERSONALIDAD', $prompt);
        $this->assertStringContainsString('INFORMACIÓN DE CONTACTO', $prompt);
        $this->assertStringContainsString('SALUDO INICIAL', $prompt);
        $this->assertStringContainsString('REGLAS DE COMUNICACIÓN', $prompt);
        $this->assertStringContainsString('Recuerda enviarme tu dirección', $prompt);
        $this->assertStringContainsString('Te derivo con el equipo', $prompt);
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
}
