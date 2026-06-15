<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Support\FormateadorCatalogoProductos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCatalogPromptTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_formatter_includes_colors_stock_and_prices(): void
    {
        $category = Category::query()->create(['name' => 'Vestidos', 'slug' => 'vestidos']);

        $product = Product::query()->create([
            'name' => 'Mariela',
            'description' => 'Vestido elegante de fiesta',
            'price' => 180,
            'price_tiktok' => 150,
            'category_id' => $category->id,
            'status' => Product::ESTADO_DISPONIBLE,
            'tags_ia' => ['elegante', 'fiesta'],
        ]);

        $product->variants()->create([
            'color' => 'Rojo',
            'sizes_stock' => ['UNICA' => 3],
        ]);

        $product->variants()->create([
            'color' => 'Negro',
            'sizes_stock' => ['UNICA' => 2],
        ]);

        $product->load(['category', 'variants']);

        $texto = (new FormateadorCatalogoProductos('$', 'UNICA'))->formatearProducto($product);

        $this->assertStringContainsString('**Mariela**', $texto);
        $this->assertStringContainsString('Precio: $ 180.00', $texto);
        $this->assertStringContainsString('TikTok: $ 150.00', $texto);
        $this->assertStringContainsString('Rojo (estándar:3', $texto);
        $this->assertStringContainsString('Negro (estándar:2', $texto);
        $this->assertStringContainsString('Tags: elegante, fiesta', $texto);
    }

    public function test_catalog_shows_extra_size_only_when_configured(): void
    {
        $product = Product::query()->create([
            'name' => 'Vestido Gala',
            'price' => 200,
            'status' => Product::ESTADO_DISPONIBLE,
        ]);

        $product->variants()->create([
            'color' => 'Azul',
            'sizes_stock' => ['UNICA' => 1, 'M' => 2],
        ]);

        $product->load('variants');

        $texto = (new FormateadorCatalogoProductos('S/', 'UNICA'))->formatearProducto($product);

        $this->assertStringContainsString('estándar:1', $texto);
        $this->assertStringContainsString('M:2', $texto);
    }

    public function test_promo_only_applies_to_normal_price_when_active(): void
    {
        $product = Product::query()->create([
            'name' => 'Mariela',
            'price' => 180,
            'price_tiktok' => 160,
            'discount' => 10,
            'discount_active' => true,
            'status' => Product::ESTADO_DISPONIBLE,
        ]);

        $product->variants()->create([
            'color' => 'Lila',
            'sizes_stock' => ['UNICA' => 5],
        ]);

        $product->load('variants');

        $texto = (new FormateadorCatalogoProductos('$', 'UNICA'))->formatearProducto($product);

        $this->assertStringContainsString('Precio: $ 180.00', $texto);
        $this->assertStringContainsString('Promo: $ 170.00', $texto);
        $this->assertStringContainsString('TikTok: $ 160.00', $texto);
    }

    public function test_inactive_promo_is_hidden_from_catalog(): void
    {
        $product = Product::query()->create([
            'name' => 'Vestido',
            'price' => 180,
            'price_tiktok' => 160,
            'discount' => 10,
            'discount_active' => false,
            'status' => Product::ESTADO_DISPONIBLE,
        ]);

        $product->load('variants');

        $texto = (new FormateadorCatalogoProductos('$'))->formatearProducto($product);

        $this->assertStringContainsString('Precio: $ 180.00', $texto);
        $this->assertStringNotContainsString('Promo:', $texto);
        $this->assertStringContainsString('TikTok: $ 160.00', $texto);
    }
}
