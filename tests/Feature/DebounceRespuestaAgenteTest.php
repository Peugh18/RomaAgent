<?php

namespace Tests\Feature;

use App\Actions\GenerarRespuestaAgente;
use App\Jobs\EsperarRespuestaAgenteJob;
use App\Jobs\GenerarRespuestaAgenteJob;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Message;
use App\Services\EncolarRespuestaAgente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DebounceRespuestaAgenteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.whatsapp.sync_token' => 'test-sync-token',
            'services.whatsapp.webhook_secret' => '',
            'services.agente.debounce_seconds' => 8,
        ]);
    }

    public function test_rafaga_de_mensajes_actualiza_debounce_al_ultimo(): void
    {
        Queue::fake();

        CompanySetting::factory()->withIaEnabled()->create();

        $headers = ['X-Roma-Sync-Token' => 'test-sync-token'];
        $mensajes = ['hola', 'linda', 'tienes', 'mariela?'];

        foreach ($mensajes as $indice => $contenido) {
            $this->postJson('/api/roma/messages', [
                'from' => '51935561361',
                'wa_id' => 'wamid.burst'.$indice,
                'content' => $contenido,
                'direction' => 'incoming',
            ], $headers)->assertOk();
        }

        Queue::assertPushed(EsperarRespuestaAgenteJob::class, 4);
        Queue::assertNotPushed(GenerarRespuestaAgenteJob::class);

        $estado = app(EncolarRespuestaAgente::class)->estadoDebounce('51935561361');
        $this->assertNotNull($estado);

        $ultimoMensaje = Message::query()
            ->where('phone_number', '51935561361')
            ->where('content', 'mariela?')
            ->first();

        $this->assertNotNull($ultimoMensaje);
        $this->assertSame($ultimoMensaje->id, $estado['message_id']);
    }

    public function test_job_antiguo_no_genera_respuesta_si_fue_reemplazado(): void
    {
        Queue::fake([GenerarRespuestaAgenteJob::class]);

        CompanySetting::factory()->withIaEnabled()->create();

        $customer = Customer::factory()->create([
            'phone_number' => '51935561361',
            'last_inbound_at' => now()->subSeconds(10),
        ]);

        $mensaje = Message::factory()->create([
            'phone_number' => $customer->phone_number,
            'direction' => 'incoming',
            'content' => 'hola',
        ]);

        Cache::put('ia_debounce:51935561361', [
            'token' => 'token-nuevo',
            'message_id' => $mensaje->id,
        ], now()->addMinutes(5));

        $job = new EsperarRespuestaAgenteJob('51935561361', 'token-viejo');
        $job->handle(app(EncolarRespuestaAgente::class), app(GenerarRespuestaAgente::class));

        Queue::assertNotPushed(GenerarRespuestaAgenteJob::class);
    }

    public function test_job_espera_si_llego_mensaje_hace_poco(): void
    {
        Queue::fake([GenerarRespuestaAgenteJob::class]);

        CompanySetting::factory()->withIaEnabled()->create();

        $customer = Customer::factory()->create([
            'phone_number' => '51935561361',
            'ia_paused' => false,
            'last_inbound_at' => now()->subSeconds(2),
        ]);

        $mensaje = Message::factory()->create([
            'phone_number' => $customer->phone_number,
            'direction' => 'incoming',
            'content' => 'mariela?',
        ]);

        $token = 'token-activo';
        Cache::put('ia_debounce:51935561361', [
            'token' => $token,
            'message_id' => $mensaje->id,
        ], now()->addMinutes(5));

        $job = new class('51935561361', $token) extends EsperarRespuestaAgenteJob
        {
            public ?int $releasedFor = null;

            public function release($delay = 0): void
            {
                $this->releasedFor = (int) $delay;
            }
        };

        $job->handle(app(EncolarRespuestaAgente::class), app(GenerarRespuestaAgente::class));

        $this->assertNotNull($job->releasedFor);
        $this->assertGreaterThanOrEqual(1, $job->releasedFor);
        Queue::assertNotPushed(GenerarRespuestaAgenteJob::class);
    }

    public function test_job_genera_respuesta_tras_debounce_completo(): void
    {
        Queue::fake();

        CompanySetting::factory()->withIaEnabled()->create();

        $customer = Customer::factory()->create([
            'phone_number' => '51935561361',
            'ia_paused' => false,
            'last_inbound_at' => now()->subSeconds(10),
        ]);

        $mensaje = Message::factory()->create([
            'phone_number' => $customer->phone_number,
            'direction' => 'incoming',
            'content' => 'mariela?',
        ]);

        $token = 'token-final';
        Cache::put('ia_debounce:51935561361', [
            'token' => $token,
            'message_id' => $mensaje->id,
        ], now()->addMinutes(5));

        $this->assertTrue(app(GenerarRespuestaAgente::class)->debeResponder($mensaje->fresh()));

        $job = new EsperarRespuestaAgenteJob('51935561361', $token);
        $job->handle(app(EncolarRespuestaAgente::class), app(GenerarRespuestaAgente::class));

        Queue::assertPushed(GenerarRespuestaAgenteJob::class, function (GenerarRespuestaAgenteJob $job) use ($mensaje): bool {
            return $job->mensajeEntrante->id === $mensaje->id;
        });

        $this->assertNull(app(EncolarRespuestaAgente::class)->estadoDebounce('51935561361'));
    }
}
