<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CompanySetting;
use App\Models\Product;
use App\Models\User;
use App\Services\ConfiguracionEmpresa;
use App\Services\ContextoConversacion;
use App\Support\FormateadorCatalogoProductos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
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
        $this->assertStringContainsString('Precio normal: $ 180.00', $texto);
        $this->assertStringContainsString('Precio TikTok: $ 150.00', $texto);
        $this->assertStringContainsString('Rojo: talla estándar (UNICA): 3 en stock', $texto);
        $this->assertStringContainsString('Negro: talla estándar (UNICA): 2 en stock', $texto);
        $this->assertStringContainsString('Stock total: 5 unidad(es)', $texto);
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

        $this->assertStringContainsString('talla estándar (UNICA): 1 en stock', $texto);
        $this->assertStringContainsString('talla M: 2 en stock', $texto);
    }

    public function test_prompt_includes_rich_product_catalog_from_database(): void
    {
        $user = User::factory()->create();
        $settings = CompanySetting::factory()->create(['moneda' => 'USD']);

        $category = Category::query()->create(['name' => 'Vestidos', 'slug' => 'vestidos-aurora']);

        Product::query()->create([
            'name' => 'Aurora',
            'price' => 140,
            'price_tiktok' => 120,
            'category_id' => $category->id,
            'status' => Product::ESTADO_DISPONIBLE,
        ])->variants()->create([
            'color' => 'Verde',
            'sizes_stock' => ['UNICA' => 1],
        ]);

        Cache::forget('contexto_prompt_completo_'.$settings->id);

        $response = $this->actingAs($user)->getJson('/api/company-settings');
        $prompt = $response->json('prompt_completo');

        $this->assertStringContainsString('# CATÁLOGO DE PRODUCTOS DISPONIBLES', $prompt);
        $this->assertStringContainsString('**Aurora**', $prompt);
        $this->assertStringContainsString('Precio normal: $ 140.00', $prompt);
        $this->assertStringContainsString('Precio TikTok: $ 120.00', $prompt);
        $this->assertStringContainsString('Verde: talla estándar (UNICA): 1 en stock', $prompt);
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

        $this->assertStringContainsString('Precio normal: $ 180.00', $texto);
        $this->assertStringContainsString('Precio normal con promo: $ 170.00', $texto);
        $this->assertStringContainsString('no aplica a TikTok', $texto);
        $this->assertStringContainsString('Precio TikTok: $ 160.00', $texto);
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

        $this->assertStringContainsString('Precio normal: $ 180.00', $texto);
        $this->assertStringNotContainsString('Precio normal con promo', $texto);
        $this->assertStringContainsString('Precio TikTok: $ 160.00', $texto);
    }
}
