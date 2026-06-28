<?php

use App\Services\Vision\ProductEmbeddingService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$service = app(ProductEmbeddingService::class);
$stats = $service->procesarCatalogoCompleto(true);
print_r($stats);
