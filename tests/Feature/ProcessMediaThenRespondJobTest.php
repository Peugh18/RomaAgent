<?php

namespace Tests\Feature;

use App\Actions\GenerarRespuestaAgente;
use App\Jobs\EsperarRespuestaAgenteJob;
use App\Jobs\GenerarRespuestaAgenteJob;
use App\Jobs\ProcessMediaThenRespondJob;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Message;
use App\Services\Media\AudioTranscriber;
use App\Services\Media\DescargadorMediaWhatsapp;
use App\Services\Media\ImageAnalyzer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ProcessMediaThenRespondJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_audio_message_dispatches_ia_job_after_transcription(): void
    {
        Queue::fake([EsperarRespuestaAgenteJob::class]);

        CompanySetting::factory()->withIaEnabled()->create([
            'agente_ia_modelo' => 'gemini-3.1-flash-lite',
        ]);

        $relativePath = 'inbound-media/audio-job.ogg';
        $fullPath = storage_path('app/public/'.$relativePath);
        if (! is_dir(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0777, true);
        }
        file_put_contents($fullPath, 'fake-ogg');

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => 'Quiero el Aurora naranja']]]],
                ],
            ]),
        ]);

        $message = Message::factory()->incoming()->create([
            'content' => '🎤 Audio',
            'metadata' => [
                'type' => 'audio',
                'local_url' => '/storage/'.$relativePath,
            ],
        ]);

        (new ProcessMediaThenRespondJob($message->id))->handle(
            app(AudioTranscriber::class),
            app(ImageAnalyzer::class),
            app(GenerarRespuestaAgente::class),
            app(DescargadorMediaWhatsapp::class),
        );

        @unlink($fullPath);

        $message->refresh();
        $this->assertSame('Quiero el Aurora naranja', $message->content);
        $this->assertSame('Quiero el Aurora naranja', $message->metadata['transcript'] ?? null);

        Queue::assertPushed(EsperarRespuestaAgenteJob::class, function (EsperarRespuestaAgenteJob $job) use ($message): bool {
            return $job->phoneNumber === $message->phone_number;
        });
    }

    public function test_image_message_dispatches_ia_job_after_vision_analysis(): void
    {
        Queue::fake([GenerarRespuestaAgenteJob::class, SendWhatsappMessageJob::class]);

        CompanySetting::factory()->withIaEnabled()->create([
            'agente_ia_modelo' => 'gemini-3.1-flash-lite',
        ]);

        $relativePath = 'inbound-media/comprobante.jpg';
        $fullPath = storage_path('app/public/'.$relativePath);
        if (! is_dir(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0777, true);
        }
        file_put_contents($fullPath, 'fake-jpeg');

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [[
                        'text' => json_encode([
                            'tipo_mensaje' => 'comprobante',
                            'encontrado' => false,
                            'nombre_vestido' => '',
                            'color' => '',
                            'stock_detectado' => 0,
                            'estrategia' => 'textual',
                        ]),
                    ]]]],
                ],
            ]),
        ]);

        $message = Message::factory()->incoming()->create([
            'content' => '📷 Imagen',
            'metadata' => [
                'type' => 'image',
                'local_url' => '/storage/'.$relativePath,
            ],
        ]);

        (new ProcessMediaThenRespondJob($message->id))->handle(
            app(AudioTranscriber::class),
            app(ImageAnalyzer::class),
            app(GenerarRespuestaAgente::class),
            app(DescargadorMediaWhatsapp::class),
        );

        @unlink($fullPath);

        $message->refresh();
        $this->assertSame('[Imagen enviada]'."\n\n".'[La clienta envió una IMAGEN/CAPTURA que parece ser un COMPROBANTE DE PAGO]', $message->content);
        $this->assertSame('comprobante', $message->metadata['vision']['inbound_profile']['tipo_mensaje'] ?? null);

        Queue::assertPushed(GenerarRespuestaAgenteJob::class);
    }

    public function test_successful_image_analysis_clears_previous_vision_failed_flag(): void
    {
        Queue::fake([EsperarRespuestaAgenteJob::class, SendWhatsappMessageJob::class]);

        CompanySetting::factory()->withIaEnabled()->create([
            'agente_ia_modelo' => 'gemini-3.1-flash-lite',
        ]);

        $relativePath = 'inbound-media/vestido-retry.jpg';
        $fullPath = storage_path('app/public/'.$relativePath);
        if (! is_dir(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0777, true);
        }
        file_put_contents($fullPath, 'fake-jpeg');

        \App\Models\Product::create([
            'id' => 1,
            'name' => 'Aurora',
            'status' => \App\Models\Product::ESTADO_DISPONIBLE,
            'price' => 120.00,
        ]);
        \App\Models\ProductVariant::create([
            'product_id' => 1,
            'color' => 'rojo',
            'image_embedding' => array_fill(0, 3072, 0.1),
            'sizes_stock' => ['estándar' => 1],
        ]);

        Http::fake([
            '*/models/*:embedContent*' => Http::response([
                'embedding' => [
                    'values' => array_fill(0, 3072, 0.1),
                ],
            ]),
            '*/models/*:generateContent*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [[
                        'text' => json_encode([
                            'es_prenda' => true,
                            'tipo_prenda' => 'vestido',
                            'zona_cuello' => ['tipo' => 'redondo'],
                            'zona_superior' => ['manga_tipo' => 'larga', 'color' => 'rojo'],
                            'zona_inferior' => ['largo' => 'maxi (hasta tobillo/piso)', 'color' => 'rojo'],
                            'paleta_colores' => ['colores' => ['rojo']],
                            'descripcion_vectorial' => 'vestido rojo largo',
                        ]),
                    ]]]],
                ],
            ]),
        ]);

        $message = Message::factory()->incoming()->create([
            'content' => '📷 Imagen',
            'metadata' => [
                'type' => 'image',
                'local_url' => '/storage/'.$relativePath,
                'vision_failed' => true,
                'vision_error' => 'No se pudo analizar',
            ],
        ]);

        (new ProcessMediaThenRespondJob($message->id))->handle(
            app(AudioTranscriber::class),
            app(ImageAnalyzer::class),
            app(GenerarRespuestaAgente::class),
            app(DescargadorMediaWhatsapp::class),
        );

        @unlink($fullPath);

        $message->refresh();
        dd($message->metadata);
        $this->assertArrayNotHasKey('vision_error', $message->metadata);
        $this->assertSame('prenda', $message->metadata['vision']['inbound_profile']['tipo_mensaje'] ?? null);

        $this->assertDatabaseHas('messages', [
            'direction' => 'outgoing',
        ]);
    }

    public function test_does_not_dispatch_ia_job_when_customer_is_paused(): void
    {
        Queue::fake([EsperarRespuestaAgenteJob::class]);

        CompanySetting::factory()->withIaEnabled()->create();

        $message = Message::factory()->incoming()->create([
            'phone_number' => '+51955554444',
            'content' => 'Hola',
            'metadata' => [
                'type' => 'audio',
                'transcript' => 'Hola tienda',
            ],
        ]);

        Customer::factory()->iaPausada()->create([
            'phone_number' => '+51955554444',
        ]);

        (new ProcessMediaThenRespondJob($message->id))->handle(
            app(AudioTranscriber::class),
            app(ImageAnalyzer::class),
            app(GenerarRespuestaAgente::class),
            app(DescargadorMediaWhatsapp::class),
        );

        Queue::assertNotPushed(EsperarRespuestaAgenteJob::class);
    }

    public function test_audio_reintenta_descarga_desde_whatsapp_raw_si_falta_local_url(): void
    {
        Queue::fake([EsperarRespuestaAgenteJob::class]);

        CompanySetting::factory()->withIaEnabled()->create([
            'agente_ia_modelo' => 'gemini-3.1-flash-lite',
        ]);

        config([
            'services.whatsapp.access_token' => 'test-token',
            'services.whatsapp.phone_number_id' => '123456789',
            'app.url' => 'https://example.test',
            'app.public_url' => 'https://example.test',
        ]);

        Http::fake([
            'graph.facebook.com/*' => Http::response(['url' => 'https://lookaside.fbsbx.com/media/audio.ogg']),
            'lookaside.fbsbx.com/*' => Http::response('fake-ogg-bytes', 200, ['Content-Type' => 'audio/ogg']),
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => 'Quiero el Mariela lila']]]],
                ],
            ]),
        ]);

        $message = Message::factory()->incoming()->create([
            'message_id' => 'wamid.audio_retry_test',
            'content' => '🎤 Audio',
            'metadata' => [
                'type' => 'audio',
                'whatsapp_raw' => [
                    'type' => 'audio',
                    'audio' => ['id' => 'media-audio-99'],
                ],
            ],
        ]);

        $job = new ProcessMediaThenRespondJob($message->id);
        $job->handle(
            app(AudioTranscriber::class),
            app(ImageAnalyzer::class),
            app(GenerarRespuestaAgente::class),
            app(DescargadorMediaWhatsapp::class),
        );

        $message->refresh();
        $this->assertStringStartsWith('/storage/inbound-media/', $message->metadata['local_url'] ?? '');
        $this->assertSame('Quiero el Mariela lila', $message->content);
        $this->assertSame('Quiero el Mariela lila', $message->metadata['transcript'] ?? null);

        Queue::assertPushed(EsperarRespuestaAgenteJob::class);
    }

    public function test_audio_reintenta_tras_error_503_de_gemini(): void
    {
        Queue::fake([EsperarRespuestaAgenteJob::class]);

        CompanySetting::factory()->withIaEnabled()->create([
            'agente_ia_modelo' => 'gemini-3.1-flash-lite',
        ]);

        $relativePath = 'inbound-media/audio-503.ogg';
        $fullPath = storage_path('app/public/'.$relativePath);
        if (! is_dir(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0777, true);
        }
        file_put_contents($fullPath, 'fake-ogg');

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'error' => [
                    'code' => 503,
                    'message' => 'This model is currently experiencing high demand.',
                    'status' => 'UNAVAILABLE',
                ],
            ], 503),
        ]);

        $message = Message::factory()->incoming()->create([
            'content' => '🎤 Audio',
            'metadata' => [
                'type' => 'audio',
                'local_url' => '/storage/'.$relativePath,
            ],
        ]);

        $job = new class($message->id) extends ProcessMediaThenRespondJob
        {
            public ?int $releasedFor = null;

            public function release($delay = 0, $attempts = null): void
            {
                $this->releasedFor = (int) $delay;
            }
        };

        $job->handle(
            app(AudioTranscriber::class),
            app(ImageAnalyzer::class),
            app(GenerarRespuestaAgente::class),
            app(DescargadorMediaWhatsapp::class),
        );

        @unlink($fullPath);

        $this->assertNotNull($job->releasedFor);
        $this->assertGreaterThanOrEqual(30, $job->releasedFor);
        Queue::assertNotPushed(EsperarRespuestaAgenteJob::class);
    }
}
