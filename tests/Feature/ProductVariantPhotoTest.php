<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductVariantPhotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_guarda_foto_de_variante_existente(): void
    {
        Storage::fake('public');
        Queue::fake();

        $user = User::factory()->create();

        $product = Product::query()->create([
            'name' => 'Aurora',
            'price' => 120,
            'status' => Product::ESTADO_DISPONIBLE,
        ]);

        $variant = $product->variants()->create([
            'color' => 'ROJO',
            'sizes_stock' => ['UNICA' => 10],
        ]);

        $response = $this->actingAs($user)->postJson(
            "/api/product-variants/{$variant->id}/photo",
            ['photo' => UploadedFile::fake()->create('aurora-rojo.jpg', 100, 'image/jpeg')],
        );

        $response->assertOk();
        $response->assertJsonStructure(['image_path', 'public_url']);

        $variant->refresh();
        $this->assertNotNull($variant->image_path);
        $this->assertStringContainsString('products/'.$product->id.'/', $variant->image_path);
        Storage::disk('public')->assertExists($variant->image_path);
    }

    public function test_actualizar_producto_con_nueva_variante_por_color_para_subir_foto(): void
    {
        $user = User::factory()->create();

        $product = Product::query()->create([
            'name' => 'Aurora',
            'price' => 120,
            'status' => Product::ESTADO_DISPONIBLE,
        ]);

        $product->variants()->create([
            'color' => 'naranja',
            'sizes_stock' => ['UNICA' => 10],
        ]);

        $response = $this->actingAs($user)->putJson("/api/products/{$product->id}", [
            'name' => 'Aurora',
            'price' => 120,
            'variants' => [
                ['color' => 'naranja', 'sizes_stock' => ['UNICA' => 10]],
                ['color' => 'ROJO', 'sizes_stock' => ['UNICA' => 10]],
            ],
        ]);

        $response->assertOk();

        $rojo = ProductVariant::query()
            ->where('product_id', $product->id)
            ->where('color', 'ROJO')
            ->first();

        $this->assertNotNull($rojo);
    }
}
