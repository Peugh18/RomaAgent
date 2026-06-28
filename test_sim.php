<?php

use App\Models\ProductVariant;
use App\Services\Vision\ProductEmbeddingService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$clientDesc = 'Vestido maxi bodycon de tejido de punto con patrón de ondas horizontales en tonos rojo, burdeos y rosa claro. Presenta cuello alto, diseño sin mangas y un ajuste ceñido al cuerpo. Incluye un cinturón integrado en la cintura con hebilla metálica central. La caída es tipo tubo hasta los tobillos con un acabado de borde liso y uniforme.';

$embService = app(ProductEmbeddingService::class);
$clientEmb = $embService->generarEmbeddingTexto($clientDesc);

$siena = ProductVariant::where('product_id', 15)->first();

$dotProduct = 0.0;
$norm1 = 0.0;
$norm2 = 0.0;
$vec1 = $clientEmb;
$vec2 = $siena->image_embedding;

for ($i = 0; $i < count($vec1); $i++) {
    $dotProduct += $vec1[$i] * $vec2[$i];
    $norm1 += $vec1[$i] * $vec1[$i];
    $norm2 += $vec2[$i] * $vec2[$i];
}
$similarity = $norm1 == 0 || $norm2 == 0 ? 0 : $dotProduct / (sqrt($norm1) * sqrt($norm2));

echo 'Similitud base: '.$similarity."\n";
