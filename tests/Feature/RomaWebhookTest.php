<?php

namespace Tests\Feature;

use App\Jobs\GenerarRespuestaAgenteJob;
use App\Jobs\ProcessMediaThenRespondJob;
use App\Models\CompanySetting;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RomaWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.whatsapp.sync_token' => 'test-sync-token',
            'services.whatsapp.webhook_secret' => '',
        ]);
    }

    public function test_rejects_unauthorized_webhook(): void
    {
        $this->postJson('/api/roma/messages', [
            'from' => '51999999999',
            'wa_id' => 'wamid.unauthorized',
            'content' => 'Hola',
            'direction' => 'incoming',
        ])->assertUnauthorized();
    }

    public function test_accepts_valid_inbound_message_and_persists_it(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/roma/messages', [
            'from' => '51999999999',
            'wa_id' => 'wamid.inbound123',
            'content' => 'Hola tienda',
            'direction' => 'incoming',
            'customer_name' => 'Cliente Test',
        ], [
            'X-Roma-Sync-Token' => 'test-sync-token',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('messages', [
            'message_id' => 'wamid.inbound123',
            'phone_number' => '51999999999',
            'content' => 'Hola tienda',
            'direction' => 'incoming',
        ]);
    }

    public function test_dispatches_ia_job_when_agent_is_enabled(): void
    {
        Queue::fake();

        CompanySetting::factory()->withIaEnabled()->create();

        $this->postJson('/api/roma/messages', [
            'from' => '51988887777',
            'wa_id' => 'wamid.ia123',
            'content' => 'Quiero comprar',
            'direction' => 'incoming',
        ], [
            'X-Roma-Sync-Token' => 'test-sync-token',
        ])->assertOk();

        Queue::assertPushed(GenerarRespuestaAgenteJob::class, function (GenerarRespuestaAgenteJob $job): bool {
            return $job->mensajeEntrante->phone_number === '51988887777';
        });
    }

    public function test_does_not_dispatch_ia_job_when_agent_is_disabled(): void
    {
        Queue::fake();

        CompanySetting::factory()->create(['agente_ia_activado' => false]);

        $this->postJson('/api/roma/messages', [
            'from' => '51977776666',
            'wa_id' => 'wamid.noia123',
            'content' => 'Hola',
            'direction' => 'incoming',
        ], [
            'X-Roma-Sync-Token' => 'test-sync-token',
        ])->assertOk();

        Queue::assertNotPushed(GenerarRespuestaAgenteJob::class);
    }

    public function test_accepts_inbound_location_message_and_persists_metadata(): void
    {
        Queue::fake();

        CompanySetting::factory()->withIaEnabled()->create();

        $response = $this->postJson('/api/roma/messages', [
            'from' => '51966665555',
            'wa_id' => 'wamid.location123',
            'direction' => 'incoming',
            'message_type' => 'location',
            'location' => [
                'latitude' => -12.046374,
                'longitude' => -77.042793,
                'name' => 'Centro Lima',
                'address' => 'Jr. de la Unión, Lima',
            ],
            'raw' => [
                'type' => 'location',
                'location' => [
                    'latitude' => -12.046374,
                    'longitude' => -77.042793,
                    'name' => 'Centro Lima',
                    'address' => 'Jr. de la Unión, Lima',
                ],
            ],
        ], [
            'X-Roma-Sync-Token' => 'test-sync-token',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('messages', [
            'message_id' => 'wamid.location123',
            'phone_number' => '51966665555',
            'content' => '📍 Ubicación compartida: Jr. de la Unión, Lima',
            'direction' => 'incoming',
        ]);

        $message = Message::query()->where('message_id', 'wamid.location123')->first();
        $this->assertNotNull($message);
        $this->assertSame('location', $message->metadata['type'] ?? null);
        $this->assertSame(-12.046374, $message->metadata['latitude'] ?? null);
        $this->assertSame(-77.042793, $message->metadata['longitude'] ?? null);
        $this->assertStringContainsString('google.com/maps', (string) ($message->metadata['maps_url'] ?? ''));

        Queue::assertPushed(GenerarRespuestaAgenteJob::class);
    }

    public function test_dispatches_media_job_for_inbound_audio(): void
    {
        Queue::fake();

        CompanySetting::factory()->withIaEnabled()->create();

        $this->postJson('/api/roma/messages', [
            'from' => '51944443333',
            'wa_id' => 'wamid.audio123',
            'direction' => 'incoming',
            'message_type' => 'audio',
            'media_url' => 'https://cdn.example.com/audio.ogg',
            'raw' => ['type' => 'audio'],
        ], [
            'X-Roma-Sync-Token' => 'test-sync-token',
        ])->assertOk();

        Queue::assertPushed(ProcessMediaThenRespondJob::class);
        Queue::assertNotPushed(GenerarRespuestaAgenteJob::class);
    }

    public function test_dispatches_media_job_for_inbound_image(): void
    {
        Queue::fake();

        CompanySetting::factory()->withIaEnabled()->create();

        $this->postJson('/api/roma/messages', [
            'from' => '51933332222',
            'wa_id' => 'wamid.image123',
            'direction' => 'incoming',
            'message_type' => 'image',
            'image_url' => 'https://cdn.example.com/comprobante.jpg',
            'raw' => ['type' => 'image'],
        ], [
            'X-Roma-Sync-Token' => 'test-sync-token',
        ])->assertOk();

        Queue::assertPushed(ProcessMediaThenRespondJob::class);
        Queue::assertNotPushed(GenerarRespuestaAgenteJob::class);
    }

    public function test_filters_messages_by_phone_number(): void
    {
        $user = User::factory()->create();

        Message::factory()->create(['phone_number' => '51111111111', 'content' => 'A']);
        Message::factory()->create(['phone_number' => '52222222222', 'content' => 'B']);

        $response = $this->actingAs($user)->getJson('/api/messages?phone_number=51111111111');

        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonPath('0.phone_number', '51111111111');
    }
}
