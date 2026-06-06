<?php

namespace Tests\Feature;

use App\Infrastructure\Whatsapp\RomaWhatsappClient;
use App\Jobs\SendWhatsappMessageJob;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SendWhatsappMessageJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_marks_message_failed_immediately_on_permanent_meta_error(): void
    {
        config([
            'services.roma.url' => 'https://roma-api.test',
            'services.roma.token' => 'test-token',
        ]);

        Http::fake([
            'https://roma-api.test/api/messages' => Http::response([
                'message' => '(#131005) Access denied',
                'code' => 131005,
                'type' => 'OAuthException',
            ], 500),
        ]);

        $message = Message::create([
            'message_id' => 'temp_test123',
            'phone_number' => '51999999999',
            'content' => 'hola',
            'direction' => 'outgoing',
            'status' => 'pending',
            'whatsapp_timestamp' => now(),
            'metadata' => ['type' => 'text'],
        ]);

        $job = new SendWhatsappMessageJob($message);
        $job->handle(app(RomaWhatsappClient::class));

        $message->refresh();

        $this->assertSame('failed', $message->status);
        $this->assertStringContainsString('token de Meta', (string) ($message->metadata['send_error'] ?? ''));
    }
}
