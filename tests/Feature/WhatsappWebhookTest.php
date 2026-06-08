<?php

namespace Tests\Feature;

use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WhatsappWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.whatsapp.verify_token' => 'test-verify-token',
            'services.whatsapp.access_token' => 'test-access-token',
            'services.whatsapp.phone_number_id' => '123456789',
        ]);
    }

    public function test_verifies_meta_webhook_challenge(): void
    {
        $this->get('/api/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=test-verify-token&hub.challenge=12345')
            ->assertOk()
            ->assertSee('12345');
    }

    public function test_rejects_invalid_verify_token(): void
    {
        $this->get('/api/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=12345')
            ->assertForbidden();
    }

    public function test_processes_inbound_text_message_from_meta(): void
    {
        Queue::fake();

        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'metadata' => ['phone_number_id' => '123456789'],
                        'messages' => [[
                            'from' => '51999999999',
                            'id' => 'wamid.meta123',
                            'timestamp' => '1710000000',
                            'type' => 'text',
                            'text' => ['body' => 'Hola desde Meta'],
                        ]],
                    ],
                ]],
            ]],
        ];

        $this->postJson('/api/whatsapp/webhook', $payload)
            ->assertOk()
            ->assertJson(['status' => 'success', 'events_processed' => 1]);

        $this->assertDatabaseHas('messages', [
            'message_id' => 'wamid.meta123',
            'phone_number' => '51999999999',
            'content' => 'Hola desde Meta',
            'direction' => 'incoming',
        ]);
    }

    public function test_processes_status_update_from_meta(): void
    {
        Message::create([
            'message_id' => 'wamid.out123',
            'phone_number' => '51999999999',
            'content' => 'hola',
            'direction' => 'outgoing',
            'status' => 'sent',
            'whatsapp_timestamp' => now(),
            'metadata' => ['type' => 'text'],
        ]);

        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'metadata' => ['phone_number_id' => '123456789'],
                        'statuses' => [[
                            'id' => 'wamid.out123',
                            'recipient_id' => '51999999999',
                            'status' => 'delivered',
                            'timestamp' => '1710000001',
                        ]],
                    ],
                ]],
            ]],
        ];

        $this->postJson('/api/whatsapp/webhook', $payload)->assertOk();

        $this->assertDatabaseHas('messages', [
            'message_id' => 'wamid.out123',
            'status' => 'delivered',
        ]);
    }
}
