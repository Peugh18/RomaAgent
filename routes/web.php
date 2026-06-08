<?php

use App\Http\Controllers\Admin\VisionEmbeddingController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryZoneController;
use App\Http\Controllers\MediaProxyController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Broadcast::routes(['middleware' => ['web', 'auth']]);

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('chat.index');
    }

    return Inertia::render('Welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('chat', [ChatController::class, 'index'])->name('chat.index');

    Route::get('pipeline', fn () => Inertia::render('Pipeline/Index'))->name('pipeline.index');
    Route::get('clientes', fn () => Inertia::render('Clientes/Index'))->name('clientes.index');

    Route::get('productos', [ProductController::class, 'index'])->name('productos.index');
    Route::get('productos/create', [ProductController::class, 'create'])->name('productos.create');
    Route::get('productos/{producto}/edit', [ProductController::class, 'edit'])->name('productos.edit');

    Route::get('categorias', [CategoryController::class, 'index'])->name('categorias.index');
    Route::get('zonas-delivery', [DeliveryZoneController::class, 'index'])->name('zonas-delivery.index');
    Route::get('configuracion', fn () => redirect()->route('configuracion.empresa'))->name('configuracion.index');
    Route::get('configuracion/empresa', fn () => Inertia::render('Configuracion/ConfiguracionEmpresa'))->name('configuracion.empresa');

    Route::get('media/proxy', MediaProxyController::class)->name('media.proxy');

    // Vision Embeddings Management
    Route::prefix('admin/vision')->group(function () {
        Route::get('/embeddings', [VisionEmbeddingController::class, 'index'])->name('admin.vision.embeddings.index');
        Route::get('/embeddings/stats', [VisionEmbeddingController::class, 'stats'])->name('admin.vision.embeddings.stats');
        Route::get('/embeddings/products', [VisionEmbeddingController::class, 'products'])->name('admin.vision.embeddings.products');
        Route::post('/embeddings/products/{product}', [VisionEmbeddingController::class, 'processProduct'])->name('admin.vision.embeddings.process');
        Route::delete('/embeddings/products/{product}', [VisionEmbeddingController::class, 'clearProduct'])->name('admin.vision.embeddings.clear');
        Route::post('/embeddings/process-batch', [VisionEmbeddingController::class, 'processBatch'])->name('admin.vision.embeddings.process-batch');
        Route::post('/embeddings/process-all', [VisionEmbeddingController::class, 'processAll'])->name('admin.vision.embeddings.process-all');
        Route::post('/embeddings/process-missing-images', [VisionEmbeddingController::class, 'processMissingImages'])->name('admin.vision.embeddings.process-missing');
        Route::get('/embeddings/learning-report', [VisionEmbeddingController::class, 'learningReport'])->name('admin.vision.embeddings.learning-report');

        // Training routes
        Route::get('/training', [VisionEmbeddingController::class, 'training'])->name('admin.vision.training');
        Route::get('/training-sessions', [VisionEmbeddingController::class, 'trainingSessions'])->name('admin.vision.training-sessions');
        Route::post('/feedback', [VisionEmbeddingController::class, 'submitFeedback'])->name('admin.vision.feedback');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
