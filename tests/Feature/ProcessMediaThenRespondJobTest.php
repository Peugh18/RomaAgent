<?php

namespace Tests\Feature;

use App\Actions\GenerarRespuestaAgente;
use App\Jobs\GenerarRespuestaAgenteJob;
use App\Jobs\ProcessMediaThenRespondJob;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Message;
use App\Services\Media\AudioTranscriber;
use App\Services\Media\DescargadorMediaWhatsapp;
use App\Services\Media\ImageAnalyzer;
use App\Services\Vision\CatalogoImageMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ProcessMediaThenRespondJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_audio_message_dispatches_ia_job_after_transcription(): void
    {
        Queue::fake([GenerarRespuestaAgenteJob::class]);

        CompanySetting::factory()->withIaEnabled()->create([
            'agente_ia_modelo' => 'gemini-2.5-flash',
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
            app(CatalogoImageMatcher::class),
            app(GenerarRespuestaAgente::class),
            app(DescargadorMediaWhatsapp::class),
        );

        @unlink($fullPath);

        $message->refresh();
        $this->assertSame('Quiero el Aurora naranja', $message->content);
        $this->assertSame('Quiero el Aurora naranja', $message->metadata['transcript'] ?? null);

        Queue::assertPushed(GenerarRespuestaAgenteJob::class, function (GenerarRespuestaAgenteJob $job) use ($message): bool {
            return $job->mensajeEntrante->id === $message->id;
        });
    }

    public function test_image_message_dispatches_ia_job_after_vision_analysis(): void
    {
        Queue::fake([GenerarRespuestaAgenteJob::class]);

        CompanySetting::factory()->withIaEnabled()->create([
            'agente_ia_modelo' => 'gemini-2.5-flash',
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
                            'tipo' => 'comprobante',
                            'es_comprobante' => true,
                            'descripcion_prenda' => 'Captura de Yape confirmando pago',
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
            app(CatalogoImageMatcher::class),
            app(GenerarRespuestaAgente::class),
            app(DescargadorMediaWhatsapp::class),
        );

        @unlink($fullPath);

        $message->refresh();
        $this->assertStringContainsString('Captura de Yape', $message->content);
        $this->assertSame('comprobante', $message->metadata['vision']['inbound_profile']['tipo'] ?? null);

        Queue::assertPushed(GenerarRespuestaAgenteJob::class);
    }

    public function test_does_not_dispatch_ia_job_when_customer_is_paused(): void
    {
        Queue::fake([GenerarRespuestaAgenteJob::class]);

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
            app(CatalogoImageMatcher::class),
            app(GenerarRespuestaAgente::class),
            app(DescargadorMediaWhatsapp::class),
        );

        Queue::assertNotPushed(GenerarRespuestaAgenteJob::class);
    }

    public function test_audio_reintenta_descarga_desde_whatsapp_raw_si_falta_local_url(): void
    {
        Queue::fake([GenerarRespuestaAgenteJob::class]);

        CompanySetting::factory()->withIaEnabled()->create([
            'agente_ia_modelo' => 'gemini-2.5-flash',
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
            app(CatalogoImageMatcher::class),
            app(GenerarRespuestaAgente::class),
            app(DescargadorMediaWhatsapp::class),
        );

        $message->refresh();
        $this->assertStringStartsWith('/storage/inbound-media/', $message->metadata['local_url'] ?? '');
        $this->assertSame('Quiero el Mariela lila', $message->content);
        $this->assertSame('Quiero el Mariela lila', $message->metadata['transcript'] ?? null);

        Queue::assertPushed(GenerarRespuestaAgenteJob::class);
    }
}
