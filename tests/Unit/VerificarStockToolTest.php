<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Agente\Tools\VerificarStockTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerificarStockToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_mapea_talla_estandar_a_clave_interna(): void
    {
        $product = Product::query()->create([
            'name' => 'Vestido Test',
            'price' => 100,
            'status' => Product::ESTADO_DISPONIBLE,
        ]);

        ProductVariant::query()->create([
            'product_id' => $product->id,
            'color' => 'Rojo',
            'sizes_stock' => ['UNICA' => 5],
        ]);

        // La IA puede pasar "talla estándar" en lugar de "UNICA"
        $result = VerificarStockTool::execute([
            'product_name' => 'Vestido Test',
            'color' => 'Rojo',
            'size' => 'talla estándar',
        ]);

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['available']);
        $this->assertSame(5, $result['qty']);
    }

    public function test_mapea_estandar_sin_talla_a_clave_interna(): void
    {
        $product = Product::query()->create([
            'name' => 'Vestido Test',
            'price' => 100,
            'status' => Product::ESTADO_DISPONIBLE,
        ]);

        ProductVariant::query()->create([
            'product_id' => $product->id,
            'color' => 'Rojo',
            'sizes_stock' => ['UNICA' => 3],
        ]);

        $result = VerificarStockTool::execute([
            'product_name' => 'Vestido Test',
            'color' => 'Rojo',
            'size' => 'estándar',
        ]);

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['available']);
        $this->assertSame(3, $result['qty']);
    }

    public function test_mapea_standar_sin_acento_a_clave_interna(): void
    {
        $product = Product::query()->create([
            'name' => 'Vestido Test',
            'price' => 100,
            'status' => Product::ESTADO_DISPONIBLE,
        ]);

        ProductVariant::query()->create([
            'product_id' => $product->id,
            'color' => 'Rojo',
            'sizes_stock' => ['UNICA' => 8],
        ]);

        $result = VerificarStockTool::execute([
            'product_name' => 'Vestido Test',
            'color' => 'Rojo',
            'size' => 'standar',
        ]);

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['available']);
        $this->assertSame(8, $result['qty']);
    }

    public function test_mapea_talla_s_directamente(): void
    {
        $product = Product::query()->create([
            'name' => 'Vestido Test',
            'price' => 100,
            'status' => Product::ESTADO_DISPONIBLE,
        ]);

        ProductVariant::query()->create([
            'product_id' => $product->id,
            'color' => 'Rojo',
            'sizes_stock' => ['S' => 2, 'M' => 5],
        ]);

        // Tallas específicas deben seguir funcionando normalmente
        $result = VerificarStockTool::execute([
            'product_name' => 'Vestido Test',
            'color' => 'Rojo',
            'size' => 'S',
        ]);

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['available']);
        $this->assertSame(2, $result['qty']);
    }

    public function test_size_vacio_usa_talla_default(): void
    {
        $product = Product::query()->create([
            'name' => 'Vestido Test',
            'price' => 100,
            'status' => Product::ESTADO_DISPONIBLE,
        ]);

        ProductVariant::query()->create([
            'product_id' => $product->id,
            'color' => 'Rojo',
            'sizes_stock' => ['UNICA' => 10],
        ]);

        $result = VerificarStockTool::execute([
            'product_name' => 'Vestido Test',
            'color' => 'Rojo',
            'size' => '',
        ]);

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['available']);
        $this->assertSame(10, $result['qty']);
    }
}
