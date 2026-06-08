<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Services\Vision\CatalogoImageMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogoImageMatcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_match_producto_y_color_con_perfiles(): void
    {
        $mariela = Product::query()->create([
            'name' => 'Mariela',
            'price' => 100,
            'status' => Product::ESTADO_DISPONIBLE,
            'tags_ia' => ['vestido', 'punto'],
            'vision_profile' => [
                'tipo_prenda' => 'vestido',
                'material_aparente' => 'punto',
                'keywords' => ['vestido', 'punto', 'mariela'],
            ],
        ]);

        $mariela->variants()->create([
            'color' => 'camel',
            'sizes_stock' => ['UNICA' => 5],
            'color_profile' => [
                'color_canonical' => 'camel',
                'colores_dominantes' => ['camel', 'beige'],
                'aliases' => ['beige', 'crema'],
            ],
        ]);

        $this->assertSame(1, Product::query()->where('status', Product::ESTADO_DISPONIBLE)->count());

        $matcher = app(CatalogoImageMatcher::class);

        $result = $matcher->match([
            'tipo' => 'producto',
            'tipo_prenda' => 'vestido',
            'material_aparente' => 'punto',
            'colores_dominantes' => ['camel'],
            'caption_cliente' => 'quiero el mariela',
        ]);

        $this->assertNotNull($result['mejor_match']);
        $this->assertSame('Mariela', $result['mejor_match']['product_name']);
        $this->assertSame('camel', $result['mejor_match']['color']);
        $this->assertGreaterThan(0.5, $result['confianza_final']);
    }

    public function test_comprobante_no_matchea_catalogo(): void
    {
        Product::query()->create([
            'name' => 'Mariela',
            'price' => 100,
            'status' => Product::ESTADO_DISPONIBLE,
        ]);

        $result = app(CatalogoImageMatcher::class)->match([
            'tipo' => 'comprobante',
            'es_comprobante' => true,
        ]);

        $this->assertSame([], $result['matches']);
        $this->assertNull($result['mejor_match']);
    }

    public function test_formatear_para_agente_incluye_match(): void
    {
        $texto = app(CatalogoImageMatcher::class)->formatearParaAgente([
            'matches' => [],
            'mejor_match' => [
                'product_id' => 1,
                'variant_id' => 2,
                'product_name' => 'Mariela',
                'color' => 'camel',
                'score' => 0.82,
                'razones' => ['producto', 'color'],
            ],
            'confianza_final' => 0.82,
            'nivel' => 'media',
        ], 'Necesito este vestido');

        $this->assertStringContainsString('Mariela', $texto);
        $this->assertStringContainsString('camel', $texto);
        $this->assertStringContainsString('Necesito este vestido', $texto);
    }
}
