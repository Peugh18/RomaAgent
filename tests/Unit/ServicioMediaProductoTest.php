<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ServicioMediaProducto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ServicioMediaProductoTest extends TestCase
{
    use RefreshDatabase;

    public function test_rewrites_localhost_storage_url_to_public_app_url(): void
    {
        config([
            'app.url' => 'http://localhost:8000',
            'app.public_url' => 'https://mi-tienda.ngrok-free.app',
            'filesystems.disks.public.url' => 'http://localhost:8000/storage',
        ]);

        $product = Product::query()->create([
            'name' => 'Mariela',
            'price' => 180,
            'status' => Product::ESTADO_DISPONIBLE,
        ]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'color' => 'Lila',
            'sizes_stock' => ['UNICA' => 5],
            'image_path' => 'products/1/lila-test.jpg',
        ]);

        $url = app(ServicioMediaProducto::class)->resolveAbsolutePublicUrl($variant);

        $this->assertSame(
            'https://mi-tienda.ngrok-free.app/storage/products/1/lila-test.jpg',
            $url,
        );
    }

    public function test_url_accessible_without_http_when_file_exists_on_disk(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/1/azul-test.jpg', 'fake-image-bytes');

        $service = app(ServicioMediaProducto::class);

        $this->assertTrue(
            $service->urlEsAccesibleParaWhatsapp('https://tunnel.ngrok-free.dev/storage/products/1/azul-test.jpg'),
        );
        $this->assertSame('products/1/azul-test.jpg', $service->rutaLocalDesdeUrlPublica(
            'https://viejo.ngrok-free.app/storage/products/1/azul-test.jpg',
        ));
    }
}
