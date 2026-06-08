<?php

namespace Tests\Feature;

use App\Infrastructure\Whatsapp\RomaWhatsappClient;
use App\Jobs\SendWhatsappMessageJob;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MetaWhatsappDirectSendTest extends TestCase
{
    use RefreshDatabase;

    public function test_sends_message_directly_to_meta_graph_api(): void
    {
        config([
            'services.whatsapp.access_token' => 'test-token',
            'services.whatsapp.phone_number_id' => '999888777',
            'services.whatsapp.graph_version' => 'v21.0',
        ]);

        Http::fake([
            'https://graph.facebook.com/v21.0/999888777/messages' => Http::response([
                'messages' => [['id' => 'wamid.direct123']],
            ], 200),
        ]);

        $message = Message::create([
            'message_id' => 'temp_direct',
            'phone_number' => '51999999999',
            'content' => 'hola directo',
            'direction' => 'outgoing',
            'status' => 'pending',
            'whatsapp_timestamp' => now(),
            'metadata' => ['type' => 'text'],
        ]);

        $job = new SendWhatsappMessageJob($message);
        $job->handle(app(RomaWhatsappClient::class));

        $message->refresh();

        $this->assertSame('sent', $message->status);
        $this->assertSame('wamid.direct123', $message->message_id);
    }
}
