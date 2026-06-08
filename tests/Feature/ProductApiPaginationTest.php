<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductApiPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_index_includes_pagination_meta_total(): void
    {
        $user = User::factory()->create();
        Product::query()->create(['name' => 'Mariela', 'price' => 180, 'status' => Product::ESTADO_DISPONIBLE]);
        Product::query()->create(['name' => 'Aurora', 'price' => 140, 'status' => Product::ESTADO_DISPONIBLE]);

        $response = $this->actingAs($user)
            ->getJson('/api/products');

        $response->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonCount(2, 'data');
    }
}
