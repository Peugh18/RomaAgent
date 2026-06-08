<?php

namespace Tests\Unit;

use App\Models\CompanySetting;
use App\Models\Product;
use App\Services\Vision\ProductEmbeddingService;
use App\Services\Vision\VectorSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProductEmbeddingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_genera_y_guarda_embedding_con_gemini_embedding_001(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'embedding' => [
                    'values' => array_fill(0, 8, 0.12),
                ],
            ], 200),
        ]);

        CompanySetting::factory()->withIaEnabled('test-gemini-key')->create();

        $product = Product::query()->create([
            'name' => 'Mariela',
            'price' => 100,
            'status' => Product::ESTADO_DISPONIBLE,
            'tags_ia' => ['vestido'],
            'vision_profile' => ['tipo_prenda' => 'vestido'],
        ]);

        $variant = $product->variants()->create([
            'color' => 'lila',
            'sizes_stock' => ['UNICA' => 3],
            'color_profile' => ['color_canonical' => 'lila'],
        ]);

        $service = app(ProductEmbeddingService::class);

        $this->assertTrue($service->aplicarEmbeddingVariante($variant));

        $variant->refresh();
        $this->assertNotNull($variant->image_embedding);
        $this->assertCount(8, $variant->image_embedding);
        $this->assertNotNull($variant->embedding_at);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'gemini-embedding-001:embedContent');
        });
    }

    public function test_busqueda_vectorial_encuentra_variante_similar(): void
    {
        $product = Product::query()->create([
            'name' => 'Mariela',
            'price' => 100,
            'status' => Product::ESTADO_DISPONIBLE,
        ]);

        $variant = $product->variants()->create([
            'color' => 'lila',
            'sizes_stock' => ['UNICA' => 3],
            'image_embedding' => [1.0, 0.0, 0.0],
            'embedding_at' => now(),
        ]);

        $results = app(VectorSearchService::class)
            ->buscarSimilares([0.99, 0.01, 0.0], 3, 0.5);

        $this->assertCount(1, $results);
        $this->assertSame($variant->id, $results->first()['variant']->id);
        $this->assertGreaterThan(0.9, $results->first()['similarity']);
    }
}
