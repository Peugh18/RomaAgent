<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessagePinTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_pin_a_message(): void
    {
        $user = User::factory()->create();
        $message = Message::factory()->create(['phone_number' => '51999999999', 'is_pinned' => false]);

        $response = $this->actingAs($user)->postJson("/api/messages/{$message->id}/pin");

        $response->assertOk();
        $this->assertTrue($message->fresh()->is_pinned);
    }

    public function test_can_unpin_a_message(): void
    {
        $user = User::factory()->create();
        $message = Message::factory()->create(['phone_number' => '51999999999', 'is_pinned' => true]);

        $response = $this->actingAs($user)->postJson("/api/messages/{$message->id}/pin");

        $response->assertOk();
        $this->assertFalse($message->fresh()->is_pinned);
    }

    public function test_pinning_unpins_previous_message_in_same_conversation(): void
    {
        $user = User::factory()->create();
        $phone = '51999999999';

        $old = Message::factory()->create(['phone_number' => $phone, 'is_pinned' => true]);
        $new = Message::factory()->create(['phone_number' => $phone, 'is_pinned' => false]);

        $response = $this->actingAs($user)->postJson("/api/messages/{$new->id}/pin");

        $response->assertOk();
        $this->assertTrue($new->fresh()->is_pinned);
        $this->assertFalse($old->fresh()->is_pinned);
    }

    public function test_pinning_does_not_affect_different_conversation(): void
    {
        $user = User::factory()->create();

        $other = Message::factory()->create(['phone_number' => '51888888888', 'is_pinned' => true]);
        $target = Message::factory()->create(['phone_number' => '51999999999', 'is_pinned' => false]);

        $this->actingAs($user)->postJson("/api/messages/{$target->id}/pin");

        // The other conversation's pin should be untouched
        $this->assertTrue($other->fresh()->is_pinned);
        $this->assertTrue($target->fresh()->is_pinned);
    }

    public function test_pin_requires_authentication(): void
    {
        $message = Message::factory()->create();

        $response = $this->postJson("/api/messages/{$message->id}/pin");

        $response->assertUnauthorized();
    }
}
