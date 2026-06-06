<?php

namespace Tests\Unit;

use App\Actions\Pedidos\EnviarFotoProductoDesdeAgente;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EnviarFotoProductoDesdeAgenteTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_absolute_public_url_from_public_app_url(): void
    {
        config([
            'app.url' => 'http://localhost:8000',
            'app.public_url' => 'https://mi-tienda.ngrok-free.app',
            'filesystems.disks.public.url' => 'http://localhost:8000/storage',
        ]);

        Storage::fake('public');
        Storage::disk('public')->put('products/1/lila-test.jpg', 'fake-image');

        $product = Product::query()->create([
            'name' => 'Mariela',
            'price' => 180,
            'status' => Product::ESTADO_DISPONIBLE,
        ]);

        ProductVariant::query()->create([
            'product_id' => $product->id,
            'color' => 'Lila',
            'sizes_stock' => ['UNICA' => 5],
            'image_path' => 'products/1/lila-test.jpg',
        ]);

        $result = app(EnviarFotoProductoDesdeAgente::class)->handle('Mariela', 'Lila');

        $this->assertTrue($result['ok']);
        $this->assertSame(
            'https://mi-tienda.ngrok-free.app/storage/products/1/lila-test.jpg',
            $result['image_url'],
        );
    }

    public function test_rejects_localhost_urls_for_whatsapp(): void
    {
        config([
            'app.url' => 'http://localhost:8000',
            'app.public_url' => 'http://localhost:8000',
            'filesystems.disks.public.url' => 'http://localhost:8000/storage',
        ]);

        $product = Product::query()->create([
            'name' => 'Aurora',
            'price' => 140,
            'status' => Product::ESTADO_DISPONIBLE,
        ]);

        ProductVariant::query()->create([
            'product_id' => $product->id,
            'color' => 'naranja',
            'sizes_stock' => ['UNICA' => 3],
            'image_path' => 'products/2/naranja-test.jpg',
        ]);

        $result = app(EnviarFotoProductoDesdeAgente::class)->handle('Aurora', 'naranja');

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('PUBLIC_APP_URL', (string) $result['error']);
    }

    public function test_rejects_unreachable_public_urls(): void
    {
        config([
            'app.url' => 'http://localhost:8000',
            'app.public_url' => 'https://tunnel-caido.ngrok-free.app',
            'filesystems.disks.public.url' => 'http://localhost:8000/storage',
        ]);

        Http::fake([
            '*' => Http::response('Not Found', 404),
        ]);

        $product = Product::query()->create([
            'name' => 'Mariela',
            'price' => 180,
            'status' => Product::ESTADO_DISPONIBLE,
        ]);

        ProductVariant::query()->create([
            'product_id' => $product->id,
            'color' => 'Azul',
            'sizes_stock' => ['UNICA' => 5],
            'image_path' => 'products/1/azul-test.jpg',
        ]);

        $result = app(EnviarFotoProductoDesdeAgente::class)->handle('Mariela', 'Azul');

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('no responde', (string) $result['error']);
    }
}
