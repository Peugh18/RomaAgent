<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_finds_customer_by_phone_across_pages(): void
    {
        $user = User::factory()->create();

        Customer::factory()->create(['phone_number' => '51111111111', 'name' => 'Ana']);
        Customer::factory()->create(['phone_number' => '52222222222', 'name' => 'Bruno']);
        Customer::factory()->create(['phone_number' => '51999888777', 'name' => 'Carla']);

        $response = $this->actingAs($user)->getJson('/api/customers?search=999888777');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Carla');
    }

    public function test_search_finds_customer_by_name(): void
    {
        $user = User::factory()->create();

        Customer::factory()->create(['phone_number' => '51111111111', 'name' => 'Mariela Lopez']);
        Customer::factory()->create(['phone_number' => '52222222222', 'name' => 'Otro Cliente']);

        $response = $this->actingAs($user)->getJson('/api/customers?search=mariela');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.phone_number', '51111111111');
    }
}
