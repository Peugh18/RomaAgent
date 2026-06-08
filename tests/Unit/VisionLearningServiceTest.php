<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Services\Vision\VisionLearningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VisionLearningServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_registra_match_detectado_como_pendiente(): void
    {
        $product = Product::query()->create([
            'name' => 'Mariela',
            'price' => 100,
            'status' => Product::ESTADO_DISPONIBLE,
        ]);

        $variant = $product->variants()->create([
            'color' => 'lila',
            'sizes_stock' => ['UNICA' => 3],
        ]);

        $service = app(VisionLearningService::class);
        $id = $service->registrarMatchDetectado($variant->id, [
            'predicted_product' => 'Mariela',
            'confianza_analisis' => 0.82,
            'estrategia' => 'hibrida',
            'tipo_prenda' => 'vestido',
        ]);

        $this->assertGreaterThan(0, $id);

        $row = DB::table('vision_learning_feedback')->where('id', $id)->first();
        $this->assertNotNull($row);
        $this->assertSame('pendiente', $row->tipo_feedback);
        $this->assertSame($variant->id, (int) $row->variant_id);
    }

    public function test_reporte_excluye_pendientes_de_tasa_de_acierto(): void
    {
        $product = Product::query()->create([
            'name' => 'Aurora',
            'price' => 90,
            'status' => Product::ESTADO_DISPONIBLE,
        ]);

        $variant = $product->variants()->create([
            'color' => 'naranja',
            'sizes_stock' => ['UNICA' => 2],
        ]);

        $service = app(VisionLearningService::class);
        $service->registrarMatchDetectado($variant->id, ['confianza_analisis' => 0.7]);
        $service->registrarFeedbackPositivo($variant->id, ['confianza_analisis' => 0.9]);
        $service->registrarFeedbackNegativo($variant->id, ['confianza_analisis' => 0.4]);

        $reporte = $service->generarReporteAprendizaje();

        $this->assertSame(1, $reporte['feedback_positivo']);
        $this->assertSame(1, $reporte['feedback_negativo']);
        $this->assertSame(1, $reporte['feedback_pendiente']);
        $this->assertSame(50.0, $reporte['tasa_acierto']);
    }
}
