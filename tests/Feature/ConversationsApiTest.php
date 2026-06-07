<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_conversations_include_ia_pause_reason(): void
    {
        $user = User::factory()->create();

        $customer = Customer::factory()->create([
            'phone_number' => '51999111222',
            'name' => 'Mariela',
            'ia_paused' => true,
            'ia_pause_reason' => 'Pago con tarjeta',
        ]);

        Message::query()->create([
            'message_id' => 'wamid.test.1',
            'phone_number' => $customer->phone_number,
            'customer_name' => $customer->name,
            'content' => 'Hola',
            'direction' => 'incoming',
            'status' => 'delivered',
            'metadata' => ['type' => 'text'],
        ]);

        $response = $this->actingAs($user)->getJson('/api/conversations');

        $response->assertOk()
            ->assertJsonFragment([
                'phone' => '51999111222',
                'ia_paused' => true,
                'ia_pause_reason' => 'Pago con tarjeta',
            ]);
    }
}
