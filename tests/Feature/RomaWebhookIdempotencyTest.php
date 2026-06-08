<?php

namespace Tests\Feature;

use App\Jobs\EsperarRespuestaAgenteJob;
use App\Jobs\ProcessMediaThenRespondJob;
use App\Models\CompanySetting;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RomaWebhookIdempotencyTest extends TestCase
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

    public function test_duplicate_webhook_does_not_dispatch_ia_again(): void
    {
        Queue::fake();

        CompanySetting::factory()->withIaEnabled()->create();

        $payload = [
            'from' => '51999999999',
            'wa_id' => 'wamid.duplicate123',
            'content' => 'Hola',
            'direction' => 'incoming',
        ];

        $headers = ['X-Roma-Sync-Token' => 'test-sync-token'];

        $this->postJson('/api/roma/messages', $payload, $headers)->assertOk();
        $this->postJson('/api/roma/messages', $payload, $headers)->assertOk();

        Queue::assertPushed(EsperarRespuestaAgenteJob::class, 1);
        $this->assertSame(1, Message::query()->where('message_id', 'wamid.duplicate123')->count());
    }

    public function test_duplicate_media_webhook_does_not_dispatch_media_job_again(): void
    {
        Queue::fake();

        CompanySetting::factory()->withIaEnabled()->create();

        $payload = [
            'from' => '51988887777',
            'wa_id' => 'wamid.image-dup',
            'direction' => 'incoming',
            'message_type' => 'image',
            'image_url' => 'https://cdn.example.com/foto.jpg',
            'raw' => ['type' => 'image'],
        ];

        $headers = ['X-Roma-Sync-Token' => 'test-sync-token'];

        $this->postJson('/api/roma/messages', $payload, $headers)->assertOk();
        $this->postJson('/api/roma/messages', $payload, $headers)->assertOk();

        Queue::assertPushed(ProcessMediaThenRespondJob::class, 1);
    }
}
