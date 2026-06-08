<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Services\Vision\AplicadorPerfilVisionVariante;
use App\Support\Vision\PerfilVisionFallback;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerfilVisionFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_construye_perfil_producto_desde_nombre_y_tags(): void
    {
        $product = Product::query()->create([
            'name' => 'Mariela',
            'price' => 100,
            'status' => Product::ESTADO_DISPONIBLE,
            'tags_ia' => ['vestido', 'punto'],
        ]);

        $profile = PerfilVisionFallback::construirPerfilProducto($product);

        $this->assertSame('fallback', $profile['origen']);
        $this->assertContains('mariela', $profile['keywords']);
        $this->assertContains('vestido', $profile['keywords']);
    }

    public function test_aliases_color_lila_incluye_violeta(): void
    {
        $aliases = PerfilVisionFallback::aliasesParaColor('lila');

        $this->assertContains('lila', $aliases);
        $this->assertContains('violeta', $aliases);
    }

    public function test_aplicador_fallback_sin_gemini(): void
    {
        $product = Product::query()->create([
            'name' => 'Aurora',
            'price' => 120,
            'status' => Product::ESTADO_DISPONIBLE,
        ]);

        $variant = $product->variants()->create([
            'color' => 'naranja',
            'sizes_stock' => ['UNICA' => 2],
            'image_path' => 'products/test.jpg',
        ]);

        $resultado = app(AplicadorPerfilVisionVariante::class)->aplicar($variant->fresh(['product']), usarGemini: false);

        $variant->refresh();
        $product->refresh();

        $this->assertFalse($resultado['gemini']);
        $this->assertTrue($resultado['fallback']);
        $this->assertNotEmpty($variant->color_profile);
        $this->assertNotEmpty($product->vision_profile);
        $this->assertSame('fallback', $variant->color_profile['origen'] ?? null);
    }
}
