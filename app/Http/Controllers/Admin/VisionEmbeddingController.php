<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Vision\ProductEmbeddingService;
use App\Services\Vision\ResolutorUrlImagenVariante;
use App\Services\Vision\VisionLearningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VisionEmbeddingController extends Controller
{
    public function __construct(
        private ProductEmbeddingService $embeddingService,
        private VisionLearningService $learningService,
        private ResolutorUrlImagenVariante $resolutorUrl,
    ) {}

    /**
     * Vista principal de gestión de embeddings
     */
    public function index()
    {
        return inertia('Admin/Vision/Embeddings', [
            'stats' => $this->getStats(),
            'recentActivity' => $this->getRecentActivity(),
        ]);
    }

    /**
     * API: Obtener estadísticas en tiempo real
     */
    public function stats(): JsonResponse
    {
        return response()->json($this->getStats());
    }

    /**
     * API: Obtener lista de productos con estado de embeddings
     */
    public function products(Request $request): JsonResponse
    {
        $query = Product::query()
            ->with('variants')
            ->withCount('variants as total_variants')
            ->withCount(['variants as variants_with_embeddings' => function ($q) {
                $q->whereNotNull('image_embedding');
            }])
            ->where('status', Product::ESTADO_DISPONIBLE);

        // Filtros
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where('name', 'like', "%{$search}%");
        }

        if ($request->filled('status')) {
            $status = $request->get('status');
            switch ($status) {
                case 'complete':
                    $query->havingRaw('variants_with_embeddings = total_variants');
                    break;
                case 'partial':
                    $query->havingRaw('variants_with_embeddings > 0 AND variants_with_embeddings < total_variants');
                    break;
                case 'none':
                    $query->having('variants_with_embeddings', 0);
                    break;
            }
        }

        $products = $query->orderBy('name')->paginate(20);

        return response()->json($products);
    }

    /**
     * API: Procesar embeddings de un producto específico
     */
    public function processProduct(Product $product): JsonResponse
    {
        $results = [
            'processed' => 0,
            'success' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($product->variants as $variant) {
            $results['processed']++;

            // Verificar si ya tiene embedding reciente
            if ($variant->embedding_at && $variant->embedding_at->gt(now()->subDays(7))) {
                continue;
            }

            $imageUrl = $this->resolutorUrl->resolver($variant);

            try {
                if ($this->embeddingService->aplicarEmbeddingVariante($variant, $imageUrl)) {
                    $results['success']++;
                } else {
                    $results['failed']++;
                    $results['errors'][] = "Variante {$variant->id} ({$variant->color}) - Error al generar embedding (revisa API key Gemini)";
                }
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = "Variante {$variant->id} ({$variant->color}) - Exception: ".$e->getMessage();
                Log::error('Embedding processing error', [
                    'variant_id' => $variant->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json($results);
    }

    /**
     * API: Procesar lote de productos
     */
    public function processBatch(Request $request): JsonResponse
    {
        $request->validate([
            'product_ids' => 'required|array',
            'product_ids.*' => 'integer|exists:products,id',
        ]);

        $productIds = $request->get('product_ids');

        // Procesar sincrónicamente (inmediato)
        $results = $this->procesarProductosBatch($productIds);

        return response()->json([
            'message' => 'Procesamiento completado',
            'products_count' => count($productIds),
            'results' => $results,
        ]);
    }

    /**
     * API: Procesar todo el catálogo
     */
    public function processAll(): JsonResponse
    {
        try {
            // Verificar que hay productos para procesar
            $productCount = Product::where('status', 'disponible')->count();
            if ($productCount === 0) {
                return response()->json([
                    'error' => 'No hay productos disponibles para procesar',
                    'message' => 'No se encontraron productos con estado disponible',
                ], 422);
            }

            // Procesar sincrónicamente (inmediato, sin queue)
            $results = $this->procesarProductosBatch(null);

            return response()->json([
                'message' => 'Procesamiento completado',
                'products_count' => $productCount,
                'results' => $results,
            ]);
        } catch (\Exception $e) {
            Log::error('Error en processAll', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Error al iniciar procesamiento',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * API: Eliminar embeddings de un producto
     */
    public function clearProduct(Product $product): JsonResponse
    {
        $cleared = $product->variants()
            ->whereNotNull('image_embedding')
            ->update([
                'image_embedding' => null,
                'embedding_at' => null,
            ]);

        return response()->json([
            'message' => 'Embeddings eliminados',
            'cleared_count' => $cleared,
        ]);
    }

    /**
     * API: Obtener reporte de aprendizaje
     */
    public function learningReport(): JsonResponse
    {
        return response()->json($this->learningService->generarReporteAprendizaje());
    }

    /**
     * API: Forzar re-procesamiento de variantes sin imagen
     */
    public function processMissingImages(): JsonResponse
    {
        $variants = ProductVariant::whereNull('image_embedding')
            ->whereHas('product', fn ($q) => $q->where('status', 'disponible'))
            ->with('product')
            ->get();

        $results = [
            'found' => $variants->count(),
            'processed' => 0,
            'success' => 0,
            'failed' => 0,
            'details' => [],
        ];

        foreach ($variants as $variant) {
            $results['processed']++;

            $imageUrl = $this->resolutorUrl->resolver($variant);

            try {
                if ($this->embeddingService->aplicarEmbeddingVariante($variant, $imageUrl)) {
                    $results['success']++;
                    $results['details'][] = [
                        'variant_id' => $variant->id,
                        'product_name' => $variant->product->name,
                        'color' => $variant->color,
                        'status' => 'success',
                    ];
                } else {
                    $results['failed']++;
                    $results['details'][] = [
                        'variant_id' => $variant->id,
                        'product_name' => $variant->product->name,
                        'color' => $variant->color,
                        'error' => 'Error generando embedding',
                    ];
                }
            } catch (\Exception $e) {
                $results['failed']++;
                $results['details'][] = [
                    'variant_id' => $variant->id,
                    'product_name' => $variant->product->name,
                    'color' => $variant->color,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json($results);
    }

    /**
     * Obtener estadísticas generales
     */
    private function getStats(): array
    {
        $totalProducts = Product::where('status', 'disponible')->count();
        $totalVariants = ProductVariant::whereHas('product', fn ($q) => $q->where('status', 'disponible'))->count();
        $withEmbeddings = ProductVariant::whereNotNull('image_embedding')
            ->whereHas('product', fn ($q) => $q->where('status', 'disponible'))
            ->count();
        $recentEmbeddings = ProductVariant::whereNotNull('embedding_at')
            ->where('embedding_at', '>', now()->subDays(7))
            ->whereHas('product', fn ($q) => $q->where('status', 'disponible'))
            ->count();

        $productsComplete = Product::where('status', 'disponible')
            ->withCount(['variants as total', 'variants as with_embeddings' => function ($q) {
                $q->whereNotNull('image_embedding');
            }])
            ->havingRaw('total = with_embeddings')
            ->count();

        return [
            'total_products' => $totalProducts,
            'total_variants' => $totalVariants,
            'variants_with_embeddings' => $withEmbeddings,
            'recent_embeddings' => $recentEmbeddings,
            'products_complete' => $productsComplete,
            'completion_percentage' => $totalVariants > 0 ? round(($withEmbeddings / $totalVariants) * 100, 2) : 0,
        ];
    }

    /**
     * Obtener actividad reciente
     */
    private function getRecentActivity(): array
    {
        return ProductVariant::whereNotNull('embedding_at')
            ->with('product')
            ->orderByDesc('embedding_at')
            ->limit(10)
            ->get()
            ->map(fn ($v) => [
                'id' => $v->id,
                'product_name' => $v->product->name,
                'color' => $v->color,
                'processed_at' => $v->embedding_at->toIso8601String(),
                'status' => $v->image_embedding ? 'success' : 'failed',
            ])
            ->toArray();
    }

    /**
     * Vista de entrenamiento de visión
     */
    public function training()
    {
        return inertia('Admin/Vision/Training', [
            'report' => $this->learningService->generarReporteAprendizaje(),
        ]);
    }

    /**
     * API: Obtener sesiones de entrenamiento recientes
     */
    public function trainingSessions(): JsonResponse
    {
        // Obtener últimas interacciones de análisis de visión con feedback pendiente
        $sessions = DB::table('vision_learning_feedback')
            ->select(
                'vision_learning_feedback.id',
                'vision_learning_feedback.variant_id',
                'vision_learning_feedback.variant_id_correcto',
                'vision_learning_feedback.tipo_feedback',
                'vision_learning_feedback.contexto_analisis',
                'products.name as product_name',
                'product_variants.color as variant_color',
                'vision_learning_feedback.created_at'
            )
            ->leftJoin('product_variants', 'vision_learning_feedback.variant_id', '=', 'product_variants.id')
            ->leftJoin('products', 'product_variants.product_id', '=', 'products.id')
            ->orderByDesc('vision_learning_feedback.created_at')
            ->limit(20)
            ->get()
            ->map(function ($session) {
                $contexto = json_decode($session->contexto_analisis, true);

                return [
                    'id' => $session->id,
                    'product_name' => $session->product_name ?? 'Desconocido',
                    'variant_color' => $session->variant_color ?? 'N/A',
                    'image_url' => $contexto['image_url'] ?? null,
                    'predicted_product' => $contexto['predicted_product'] ?? 'Desconocido',
                    'is_correct' => match ($session->tipo_feedback) {
                        'positivo' => true,
                        'negativo' => false,
                        default => null,
                    },
                    'created_at' => $session->created_at,
                ];
            });

        return response()->json($sessions);
    }

    /**
     * API: Enviar feedback de entrenamiento
     */
    public function submitFeedback(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => 'required|integer|exists:vision_learning_feedback,id',
            'is_correct' => 'required|boolean',
            'correct_variant_id' => 'nullable|integer|exists:product_variants,id',
        ]);

        try {
            $session = DB::table('vision_learning_feedback')
                ->where('id', $validated['session_id'])
                ->first();

            if (! $session) {
                return response()->json(['error' => 'Sesión no encontrada'], 404);
            }

            if ($validated['is_correct']) {
                DB::table('vision_learning_feedback')
                    ->where('id', $validated['session_id'])
                    ->update(['tipo_feedback' => 'positivo', 'updated_at' => now()]);
            } else {
                DB::table('vision_learning_feedback')
                    ->where('id', $validated['session_id'])
                    ->update([
                        'tipo_feedback' => 'negativo',
                        'variant_id_correcto' => $validated['correct_variant_id'],
                        'updated_at' => now(),
                    ]);
            }

            return response()->json([
                'message' => 'Feedback registrado correctamente',
                'success' => true,
            ]);
        } catch (\Exception $e) {
            Log::error('Error submitting feedback', [
                'session_id' => $validated['session_id'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Error al registrar feedback',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Procesar productos batch sincrónicamente (sin queue)
     */
    private function procesarProductosBatch(?array $productIds): array
    {
        $results = [
            'processed' => 0,
            'success' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        $query = Product::where('status', Product::ESTADO_DISPONIBLE)
            ->with('variants');

        if ($productIds !== null) {
            $query->whereIn('id', $productIds);
        }

        $query->chunk(10, function ($products) use (&$results) {
            foreach ($products as $product) {
                foreach ($product->variants as $variant) {
                    $results['processed']++;

                    // Verificar si ya tiene embedding reciente
                    if ($variant->embedding_at && $variant->embedding_at->gt(now()->subDays(7))) {
                        $results['skipped'] = ($results['skipped'] ?? 0) + 1;

                        continue;
                    }

                    $imageUrl = $this->resolutorUrl->resolver($variant);

                    try {
                        if ($this->embeddingService->aplicarEmbeddingVariante($variant, $imageUrl)) {
                            $results['success']++;
                        } else {
                            $results['failed']++;
                            $results['errors'][] = "{$product->name} / {$variant->color} - Error Gemini";
                        }
                    } catch (\Exception $e) {
                        $results['failed']++;
                        $results['errors'][] = "{$product->name} / {$variant->color} - {$e->getMessage()}";
                    }
                }
            }
        });

        return $results;
    }
}
