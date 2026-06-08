<?php

namespace Tests\Unit;

use App\Models\CompanySetting;
use App\Models\Product;
use App\Services\Vision\HybridImageMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use ReflectionClass;
use Tests\TestCase;

class HybridImageMatcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_normaliza_pesos_cuando_contexto_no_aporta(): void
    {
        $matcher = app(HybridImageMatcher::class);
        $method = (new ReflectionClass($matcher))->getMethod('calcularScoreCombinado');
        $method->setAccessible(true);

        $score = $method->invoke($matcher, 0.9225, 0.774, ['tipo_prenda' => 'vestido']);

        $this->assertGreaterThan(0.75, $score);
        $this->assertLessThanOrEqual(1.0, $score);
    }

    public function test_match_hibrido_detecta_aurora_rojo_con_alta_confianza_textual(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'embedding' => [
                    'values' => array_fill(0, 8, 0.5),
                ],
            ], 200),
        ]);

        CompanySetting::factory()->withIaEnabled('test-gemini-key')->create();

        $aurora = Product::query()->create([
            'name' => 'Aurora',
            'price' => 120,
            'status' => Product::ESTADO_DISPONIBLE,
            'vision_profile' => [
                'tipo_prenda' => 'vestido',
                'material_aparente' => 'tejido de punto',
                'patron' => 'zigzag',
                'keywords' => ['vestido rojo', 'vestido zigzag', 'cuello alto', 'sin mangas'],
            ],
        ]);

        $aurora->variants()->create([
            'color' => 'ROJO',
            'sizes_stock' => ['UNICA' => 5],
            'color_profile' => [
                'color_canonical' => 'rojo',
                'colores_dominantes' => ['rojo', 'granate'],
            ],
            'image_embedding' => array_fill(0, 8, 0.5),
        ]);

        $aurora->variants()->create([
            'color' => 'naranja',
            'sizes_stock' => ['UNICA' => 3],
            'image_embedding' => array_fill(0, 8, 0.1),
        ]);

        $inboundProfile = [
            'tipo' => 'producto',
            'tipo_prenda' => 'vestido',
            'material_aparente' => 'punto',
            'color_dominante' => 'rojo',
            'colores_dominantes' => ['rojo', 'granate', 'rosa claro'],
            'descripcion_prenda' => 'Vestido midi ajustado de punto con cuello alto y sin mangas, patrón de ondas en tonos rojos.',
            'caption_cliente' => 'Tienes este vestido??',
        ];

        $resultado = app(HybridImageMatcher::class)->matchHibrido($inboundProfile, 'Tienes este vestido??');

        $this->assertNotNull($resultado['mejor_match']);
        $this->assertSame('Aurora', $resultado['mejor_match']['product_name']);
        $this->assertSame('ROJO', $resultado['mejor_match']['color']);
        $this->assertGreaterThanOrEqual(0.75, $resultado['confianza_final']);
    }
}
