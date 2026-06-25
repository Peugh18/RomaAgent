<?php

use App\Models\ProductVariant;

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$recientes = ProductVariant::where('embedding_at', '>=', now()->subHours(2))->count();
echo "Total de variantes con embedding_at actualizado en las ultimas 2 horas: " . $recientes . "\n\n";

$variants = ProductVariant::with('product')->limit(10)->get();

echo "10 Ejemplos Reales:\n";
echo "--------------------------------------------------\n";

foreach ($variants as $variant) {
    $product = $variant->product;
    if (!$product) continue;
    $vision = $product->vision_profile ?? [];
    
    $tipoPrenda = ucfirst($vision['tipo_prenda'] ?? $product->category->name ?? 'Prenda');
    $nombreProducto = $product->name ?? '';
    
    $partes = [];
    if (!empty($nombreProducto)) {
        if (stripos($nombreProducto, $tipoPrenda) === false) {
            $partes[] = "{$tipoPrenda} {$nombreProducto}";
        } else {
            $partes[] = ucfirst($nombreProducto);
        }
    } else {
        $partes[] = $tipoPrenda;
    }
    
    if (!empty($variant->color)) {
        $partes[] = "Color " . mb_strtolower($variant->color);
    }
    if (!empty($vision['patron'])) {
        $partes[] = "Diseño " . mb_strtolower($vision['patron']);
    }
    if (!empty($vision['material_aparente'])) {
        $partes[] = "Material " . mb_strtolower($vision['material_aparente']);
    }
    if (isset($vision['detalles']) && is_array($vision['detalles']) && !empty($vision['detalles'])) {
        $detallesStr = implode(', ', array_map('mb_strtolower', $vision['detalles']));
        $partes[] = "Detalles: {$detallesStr}";
    }
    if (!empty($vision['keywords'])) {
        $kw = is_array($vision['keywords']) ? implode(', ', $vision['keywords']) : $vision['keywords'];
        $partes[] = "Ideal para " . mb_strtolower($kw);
    }
    if (!empty($product->description)) {
        $desc = rtrim(trim($product->description), '.');
        if (!empty($desc)) {
            $partes[] = $desc;
        }
    }
    
    $descripcion = implode('. ', $partes) . '.';
    $descripcion = preg_replace('/\s+/', ' ', $descripcion);
    $descripcion = preg_replace('/\.{2,}/', '.', $descripcion);
    $descripcion = str_replace(' .', '.', $descripcion);
    $descripcion = trim($descripcion);
    
    echo "Product ID: " . $product->id . "\n";
    echo "Variant ID: " . $variant->id . "\n";
    echo "Texto Normalizado: " . $descripcion . "\n";
    echo "--------------------------------------------------\n";
}
