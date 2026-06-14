<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Vision\ProductEmbeddingService;
use Illuminate\Console\Command;

class ProcessCatalogEmbeddings extends Command
{
    protected $signature = 'catalogo:embeddings 
                            {--force : Reprocesar todos los productos}
                            {--chunk=10 : Productos por chunk}
                            {--dry-run : Simular sin procesar}';

    protected $description = 'Genera embeddings vectoriales para todo el catálogo de productos';

    public function handle(ProductEmbeddingService $embeddingService): int
    {
        $this->info('🚀 Iniciando procesamiento de embeddings del catálogo...');

        $force = $this->option('force');
        $chunk = (int) $this->option('chunk');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('⚠️  MODO DRY RUN - No se procesará nada');
        }

        // Mostrar estadísticas iniciales
        $this->mostrarEstadisticasIniciales();

        if ($dryRun) {
            $this->info('✅ Dry run completado');

            return self::SUCCESS;
        }

        // Procesar con barra de progreso
        $this->withProgressBar(1, function () use ($embeddingService, $force) {
            $stats = $embeddingService->procesarCatalogoCompleto($force);

            $this->newLine();
            $this->table(
                ['Métrica', 'Cantidad'],
                [
                    ['Procesados', $stats['processed']],
                    ['Exitosos', $stats['success']],
                    ['Fallidos', $stats['failed']],
                    ['Omitidos (recientes)', $stats['skipped']],
                ]
            );
        });

        $this->info('✅ Procesamiento completado');

        return self::SUCCESS;
    }

    private function mostrarEstadisticasIniciales(): void
    {
        $totalProducts = Product::where('status', 'disponible')->count();
        $totalVariants = ProductVariant::whereHas('product', fn ($q) => $q->where('status', 'disponible'))->count();
        $conEmbeddings = ProductVariant::whereNotNull('image_embedding')->count();
        $recientes = ProductVariant::whereNotNull('embedding_at')
            ->where('embedding_at', '>', now()->subDays(7))
            ->count();

        $this->table(
            ['Métrica', 'Cantidad'],
            [
                ['Productos disponibles', $totalProducts],
                ['Variantes totales', $totalVariants],
                ['Con embeddings', $conEmbeddings],
                ['Embeddings recientes (< 7 días)', $recientes],
                ['Por procesar', max(0, $totalVariants - $recientes)],
            ]
        );
    }
}
