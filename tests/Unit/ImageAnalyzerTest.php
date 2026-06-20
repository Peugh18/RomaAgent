<?php

namespace Tests\Unit;

use App\Models\CompanySetting;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Media\ImageAnalyzer;
use App\Services\Vision\ProductEmbeddingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ImageAnalyzerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CompanySetting::factory()->withIaEnabled()->create([
            'agente_ia_modelo' => 'gemini-2.5-flash-lite',
        ]);
    }

    public function test_analyze_detects_comprobante_immediately(): void
    {
        $relativePath = 'inbound-media/test-comprobante.jpg';
        $fullPath = storage_path('app/public/'.$relativePath);
        if (! is_dir(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0777, true);
        }
        file_put_contents($fullPath, 'fake-jpeg-bytes');

        // Mock para la llamada de comprobante
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [[
                        'text' => json_encode([
                            'es_comprobante' => true,
                            'tipo_mensaje' => 'comprobante',
                        ]),
                    ]]]],
                ],
            ]),
        ]);

        $result = app(ImageAnalyzer::class)->analyzeUrl('/storage/'.$relativePath);

        @unlink($fullPath);

        $this->assertIsArray($result);
        $this->assertTrue($result['inbound_profile']['es_comprobante'] ?? false);
        $this->assertSame('comprobante', $result['inbound_profile']['tipo_mensaje'] ?? '');

        // Verifica que se llamó una vez y terminó ahí (no busca embedding)
        Http::assertSentCount(1);
    }

    public function test_analyze_uses_cosine_similarity_when_not_comprobante(): void
    {
        $relativePath = 'inbound-media/test-vestido.jpg';
        $fullPath = storage_path('app/public/'.$relativePath);
        if (! is_dir(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0777, true);
        }
        file_put_contents($fullPath, 'fake-jpeg-bytes');

        $product = Product::create([
            'name' => 'Vestido Elegante',
            'status' => 'disponible',
            'description' => 'Test',
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'color' => 'Rojo',
            'sizes_stock' => ['S' => 5],
            'image_embedding' => [0.1, 0.2, 0.3, 0.4],
        ]);

        // Mock para que ProductEmbeddingService devuelva un embedding muy similar
        $this->mock(ProductEmbeddingService::class, function ($mock) {
            $mock->shouldReceive('generarEmbeddingTexto')
                ->once()
                ->andReturn([0.1, 0.2, 0.3, 0.4]); // Mismo embedding, similitud 1.0
        });

        // Mock para las llamadas consecutivas de Gemini (detección y luego extracción)
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push([
                    'candidates' => [
                        ['content' => ['parts' => [[
                            'text' => json_encode([
                                'es_comprobante' => false,
                                'tipo_mensaje' => 'producto',
                            ]),
                        ]]]],
                    ],
                ])
                ->push([
                    'candidates' => [
                        ['content' => ['parts' => [[
                            'text' => json_encode([
                                'es_prenda' => true,
                                'descripcion_vectorial' => 'vestido rojo elegante de fiesta',
                            ]),
                        ]]]],
                    ],
                ]),
        ]);

        $result = app(ImageAnalyzer::class)->analyzeUrl('/storage/'.$relativePath);

        @unlink($fullPath);

        $this->assertIsArray($result);
        $this->assertTrue($result['inbound_profile']['encontrado'] ?? false);
        $this->assertNotEmpty($result['inbound_profile']['matches']);
        $this->assertSame($variant->product_id, $result['inbound_profile']['matches'][0]['id_producto']);
        $this->assertSame('Rojo', $result['inbound_profile']['matches'][0]['color']);

        // La similitud debería ser 1.0 o muy cercana
        $this->assertGreaterThan(0.99, $result['inbound_profile']['matches'][0]['similitud']);
    }
}
