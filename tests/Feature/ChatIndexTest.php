<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ChatIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_chat(): void
    {
        $this->get(route('chat.index'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_open_whatsapp_chat(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('chat.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Chat/Index'));
    }
}
