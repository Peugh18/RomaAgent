<?php

namespace App\Console\Commands;

use App\Models\ProductVariant;
use App\Services\ConfiguracionAgente;
use App\Services\Vision\VisionLearningService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Comando para diagnosticar el sistema de visión
 *
 * USO:
 *   php artisan vision:diagnostics    # Diagnóstico completo
 */
class VisionDiagnosticsCommand extends Command
{
    protected $signature = 'vision:diagnostics
                            {--fix : Intentar auto-corregir problemas}';

    protected $description = 'Diagnosticar sistema de visión y reconocimiento de imágenes';

    public function handle(ConfiguracionAgente $configuracion, VisionLearningService $learningService): int
    {
        $this->info('🔍 Diagnóstico del Sistema de Visión');
        $this->line('');

        $this->checkApiKey($configuracion);
        $this->checkEmbeddings();
        $this->checkLearningData();
        $this->checkMissingImages();

        return self::SUCCESS;
    }

    private function checkApiKey(ConfiguracionAgente $configuracion): void
    {
        $this->line('✓ API Key Gemini:');
        $apiKey = $configuracion->obtenerApiKey();

        if ($apiKey) {
            $masked = substr($apiKey, 0, 8).'...'.substr($apiKey, -4);
            $this->line("  Configurada: {$masked}");
        } else {
            $this->error('  ❌ No configurada - El sistema no funcionará');
        }
        $this->line('');
    }

    private function checkEmbeddings(): void
    {
        $this->line('✓ Embeddings de Productos:');

        $totalVariants = ProductVariant::count();
        $withEmbeddings = ProductVariant::whereNotNull('image_embedding')->count();
        $percentage = $totalVariants > 0 ? round(($withEmbeddings / $totalVariants) * 100, 2) : 0;

        $this->line("  Total variantes: {$totalVariants}");
        $this->line("  Con embeddings: {$withEmbeddings} ({$percentage}%)");

        if ($percentage < 50) {
            $this->warn('  ⚠️ Menos del 50% tiene embeddings - Ejecuta: php artisan catalogo:embeddings');
        } else {
            $this->info('  ✓ Cobertura aceptable');
        }
        $this->line('');
    }

    private function checkLearningData(): void
    {
        $this->line('✓ Datos de Aprendizaje:');

        $feedbackCount = DB::table('vision_learning_feedback')->count();
        $recentFeedback = DB::table('vision_learning_feedback')
            ->where('created_at', '>', now()->subDays(7))
            ->count();

        $this->line("  Total feedback: {$feedbackCount}");
        $this->line("  Feedback reciente (7 días): {$recentFeedback}");

        if ($feedbackCount === 0) {
            $this->warn('  ⚠️ Sin feedback confirmado — se registran matches automáticos cuando clientas envían fotos');
        }
        $this->line('');
    }

    private function checkMissingImages(): void
    {
        $this->line('✓ Productos sin Imágenes:');

        $variantsWithoutImages = ProductVariant::whereNull('image_embedding')
            ->whereHas('product', fn ($q) => $q->where('status', 'disponible'))
            ->with('product')
            ->limit(10)
            ->get();

        $count = ProductVariant::whereNull('image_embedding')
            ->whereHas('product', fn ($q) => $q->where('status', 'disponible'))
            ->count();

        $this->line("  Productos sin imagen/embedding: {$count}");

        if ($count > 0 && $this->option('verbose')) {
            $this->line('  Primeros 10:');
            foreach ($variantsWithoutImages as $variant) {
                $this->line("    - {$variant->product->name} / {$variant->color}");
            }
        }

        if ($count > 0) {
            $this->warn('  ⚠️ Usa la vista de Embeddings para procesar imágenes faltantes');
        }
        $this->line('');
    }
}
