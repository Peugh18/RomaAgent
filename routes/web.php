<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ChatController;
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
    Route::get('configuracion/empresa', fn () => \Inertia\Inertia::render('Configuracion/ConfiguracionEmpresa'))->name('configuracion.empresa');

    Route::get('media/proxy', MediaProxyController::class)->name('media.proxy');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
