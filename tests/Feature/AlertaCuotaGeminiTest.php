<?php

namespace Tests\Feature;

use App\Models\LogIA;
use App\Models\User;
use App\Services\AlertaCuotaGemini;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AlertaCuotaGeminiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_endpoint_devuelve_alerta_cuando_esta_marcada_en_cache(): void
    {
        $user = User::factory()->create();
        $servicio = app(AlertaCuotaGemini::class);
        $servicio->marcar('Quota exceeded for model gemini-2.5-flash', 90);

        $response = $this->actingAs($user)->getJson('/api/alerta-cuota-gemini');

        $response->assertOk()
            ->assertJsonPath('alerta.activa', true)
            ->assertJsonPath('alerta.titulo', 'Cuota de Gemini agotada')
            ->assertJsonPath('alerta.reintentar_en_segundos', 90);
    }

    public function test_endpoint_devuelve_alerta_desde_ultimo_error_429_reciente(): void
    {
        $user = User::factory()->create();

        LogIA::create([
            'tipo' => 'error',
            'phone_number' => '51999999999',
            'modelo' => 'gemini-2.5-flash',
            'http_status' => 429,
            'error_mensaje' => 'Resource has been exhausted',
            'error_codigo' => 'RESOURCE_EXHAUSTED',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($user)->getJson('/api/alerta-cuota-gemini');

        $response->assertOk()
            ->assertJsonPath('alerta.activa', true)
            ->assertJsonPath('alerta.desde_log', true);
    }

    public function test_estado_ia_limpia_alerta_cuando_prueba_api_es_exitosa(): void
    {
        $user = User::factory()->create();

        app(AlertaCuotaGemini::class)->marcar('Cuota agotada', 60);

        \Illuminate\Support\Facades\Http::fake([
            'generativelanguage.googleapis.com/*' => \Illuminate\Support\Facades\Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => 'OK']]]],
                ],
            ]),
        ]);

        \App\Models\CompanySetting::factory()->withIaEnabled()->create();

        $response = $this->actingAs($user)->getJson('/api/estado-ia');

        $response->assertOk()
            ->assertJsonPath('prueba_api.exitosa', true)
            ->assertJsonPath('alerta_cuota', null);

        $this->assertNull(app(AlertaCuotaGemini::class)->obtener());
    }

    public function test_job_marca_alerta_cuando_falla_por_cuota(): void
    {
        $exception = new \App\Exceptions\GeminiQuotaExceededException('Quota exceeded', 45);

        $job = new \App\Jobs\GenerarRespuestaAgenteJob(
            \App\Models\Message::factory()->incoming()->create()
        );

        $job->failed($exception);

        $alerta = app(AlertaCuotaGemini::class)->obtener();

        $this->assertNotNull($alerta);
        $this->assertTrue($alerta['activa']);
        $this->assertSame('Quota exceeded', $alerta['mensaje']);
    }
}
