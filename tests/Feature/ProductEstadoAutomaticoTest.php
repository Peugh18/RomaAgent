<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductEstadoAutomaticoTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_becomes_agotado_when_stock_is_zero(): void
    {
        $product = Product::query()->create([
            'name' => 'Vestido',
            'price' => 100,
            'status' => Product::ESTADO_DISPONIBLE,
        ]);

        $variant = $product->variants()->create([
            'color' => 'Rojo',
            'sizes_stock' => ['UNICA' => 5],
        ]);

        $variant->update(['sizes_stock' => ['UNICA' => 0]]);

        $product->refresh();

        $this->assertSame(Product::ESTADO_AGOTADO, $product->status);
    }

    public function test_product_becomes_disponible_when_stock_returns(): void
    {
        $product = Product::query()->create([
            'name' => 'Vestido',
            'price' => 100,
            'status' => Product::ESTADO_AGOTADO,
        ]);

        $variant = $product->variants()->create([
            'color' => 'Rojo',
            'sizes_stock' => ['UNICA' => 0],
        ]);

        $variant->update(['sizes_stock' => ['UNICA' => 3]]);

        $product->refresh();

        $this->assertSame(Product::ESTADO_DISPONIBLE, $product->status);
    }

    public function test_oculto_status_is_not_changed_by_stock_sync(): void
    {
        $product = Product::query()->create([
            'name' => 'Vestido',
            'price' => 100,
            'status' => Product::ESTADO_OCULTO,
        ]);

        $product->variants()->create([
            'color' => 'Rojo',
            'sizes_stock' => ['UNICA' => 10],
        ]);

        $product->refresh();

        $this->assertSame(Product::ESTADO_OCULTO, $product->status);
    }

    public function test_api_update_uses_oculto_flag_and_auto_status(): void
    {
        $user = User::factory()->create();

        $product = Product::query()->create([
            'name' => 'Mariela',
            'price' => 180,
            'status' => Product::ESTADO_DISPONIBLE,
        ]);

        $variant = $product->variants()->create([
            'color' => 'Lila',
            'sizes_stock' => ['UNICA' => 2],
        ]);

        $this->actingAs($user)->putJson("/api/products/{$product->id}", [
            'name' => 'Mariela',
            'price' => 180,
            'oculto' => false,
            'variants' => [
                [
                    'id' => $variant->id,
                    'color' => 'Lila',
                    'sizes_stock' => ['UNICA' => 0],
                ],
            ],
        ])->assertOk();

        $product->refresh();
        $this->assertSame(Product::ESTADO_AGOTADO, $product->status);
    }
}
