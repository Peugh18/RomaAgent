<?php

namespace Tests\Feature;

use App\Jobs\SendWhatsappMessageJob;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReenviarMensajeWhatsappTest extends TestCase
{
    use RefreshDatabase;

    public function test_resend_failed_outgoing_message_refreshes_image_url_and_queues_job(): void
    {
        Queue::fake();

        Storage::fake('public');
        Storage::disk('public')->put('products/1/azul-test.jpg', 'fake-image');

        config([
            'app.url' => 'https://caravan-cycle-elixir.ngrok-free.dev',
            'app.public_url' => 'https://caravan-cycle-elixir.ngrok-free.dev',
        ]);

        $user = User::factory()->create();

        $message = Message::query()->create([
            'message_id' => 'wamid.old123',
            'phone_number' => '51920772139',
            'content' => 'Aquí está Mariela en azul 💕',
            'direction' => 'outgoing',
            'status' => 'failed',
            'whatsapp_timestamp' => now(),
            'metadata' => [
                'type' => 'image',
                'image_url' => 'https://tunnel-viejo.ngrok-free.app/storage/products/1/azul-test.jpg',
                'send_error' => 'Meta no pudo descargar la imagen',
            ],
        ]);

        $response = $this->actingAs($user)->postJson("/api/messages/{$message->id}/resend");

        $response->assertOk()
            ->assertJsonPath('data.status', 'pending');

        $message->refresh();

        $this->assertSame('pending', $message->status);
        $this->assertStringStartsWith('out_', $message->message_id);
        $this->assertSame(
            'https://caravan-cycle-elixir.ngrok-free.dev/storage/products/1/azul-test.jpg',
            $message->metadata['image_url'] ?? null,
        );
        $this->assertArrayNotHasKey('send_error', $message->metadata ?? []);
        $this->assertNotEmpty($message->metadata['resend_history'] ?? null);

        Queue::assertPushed(SendWhatsappMessageJob::class);
    }

    public function test_resend_rejects_non_failed_messages(): void
    {
        $user = User::factory()->create();

        $message = Message::query()->create([
            'message_id' => 'wamid.sent123',
            'phone_number' => '51920772139',
            'content' => 'Hola',
            'direction' => 'outgoing',
            'status' => 'sent',
            'whatsapp_timestamp' => now(),
            'metadata' => ['type' => 'text'],
        ]);

        $this->actingAs($user)
            ->postJson("/api/messages/{$message->id}/resend")
            ->assertStatus(422);
    }

    public function test_resend_rejects_incoming_messages(): void
    {
        $user = User::factory()->create();

        $message = Message::query()->create([
            'message_id' => 'wamid.in123',
            'phone_number' => '51920772139',
            'content' => 'Hola',
            'direction' => 'incoming',
            'status' => 'failed',
            'whatsapp_timestamp' => now(),
            'metadata' => ['type' => 'text'],
        ]);

        $this->actingAs($user)
            ->postJson("/api/messages/{$message->id}/resend")
            ->assertStatus(422);
    }

    public function test_resend_failed_text_message_without_image_check(): void
    {
        Queue::fake();

        config([
            'services.whatsapp.access_token' => 'test-token',
            'services.whatsapp.phone_number_id' => '999888777',
            'services.whatsapp.graph_version' => 'v21.0',
        ]);

        Http::fake([
            'https://graph.facebook.com/v21.0/999888777/messages' => Http::response([
                'messages' => [['id' => 'wamid.newtext123']],
            ], 200),
        ]);

        $user = User::factory()->create();

        $message = Message::query()->create([
            'message_id' => 'wamid.failtext',
            'phone_number' => '51920772139',
            'content' => 'Mensaje de prueba',
            'direction' => 'outgoing',
            'status' => 'failed',
            'whatsapp_timestamp' => now(),
            'metadata' => [
                'type' => 'text',
                'send_error' => 'Error temporal',
            ],
        ]);

        $this->actingAs($user)
            ->postJson("/api/messages/{$message->id}/resend")
            ->assertOk();

        Queue::assertPushed(SendWhatsappMessageJob::class);
    }
}
