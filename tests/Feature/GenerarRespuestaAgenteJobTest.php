<?php

namespace Tests\Feature;

use App\Actions\GenerarRespuestaAgente;
use App\Jobs\GenerarRespuestaAgenteJob;
use App\Models\CompanySetting;
use App\Models\LogIA;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GenerarRespuestaAgenteJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_and_queues_whatsapp_reply_on_success(): void
    {
        CompanySetting::factory()->withIaEnabled()->create([
            'agente_ia_modelo' => 'gemini-2.5-flash-lite',
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => 'Hola, ¿en qué te ayudo?']]]],
                ],
                'usageMetadata' => [
                    'promptTokenCount' => 10,
                    'candidatesTokenCount' => 8,
                    'totalTokenCount' => 18,
                ],
            ]),
            'https://graph.facebook.com/v21.0/999888777/messages' => Http::response([
                'messages' => [['id' => 'wamid.testreply123']],
            ]),
        ]);

        config([
            'services.whatsapp.access_token' => 'test-token',
            'services.whatsapp.phone_number_id' => '999888777',
            'services.whatsapp.graph_version' => 'v21.0',
        ]);

        $incoming = Message::factory()->incoming()->create([
            'content' => 'Tienen vestidos?',
        ]);

        $job = new GenerarRespuestaAgenteJob($incoming);
        $job->handle(app(GenerarRespuestaAgente::class));

        $this->assertDatabaseHas('messages', [
            'phone_number' => $incoming->phone_number,
            'direction' => 'outgoing',
            'content' => 'Hola, ¿en qué te ayudo?',
        ]);

        $this->assertDatabaseHas('logs_ia', [
            'tipo' => 'response',
            'phone_number' => $incoming->phone_number,
        ]);
    }

    public function test_logs_error_when_gemini_returns_empty_response(): void
    {
        CompanySetting::factory()->withIaEnabled()->create([
            'agente_ia_modelo' => 'gemini-2.5-flash-lite',
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'error' => [
                    'code' => 429,
                    'message' => 'Quota exceeded',
                    'status' => 'RESOURCE_EXHAUSTED',
                ],
            ], 429),
        ]);

        $incoming = Message::factory()->incoming()->create([
            'content' => 'Hola',
        ]);

        $job = new GenerarRespuestaAgenteJob($incoming);
        $job->handle(app(GenerarRespuestaAgente::class));

        $this->assertDatabaseMissing('messages', [
            'phone_number' => $incoming->phone_number,
            'direction' => 'outgoing',
        ]);

        $this->assertSame(1, LogIA::query()->where('tipo', 'error')->count());
    }
}
