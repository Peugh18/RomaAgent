<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_see_welcome_page()
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page->component('Welcome'));
    }

    public function test_authenticated_users_are_redirected_to_chat()
    {
        $user = \App\Models\User::factory()->create();

        $this->actingAs($user)
            ->get('/')
            ->assertRedirect(route('chat.index'));
    }
}
