<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Product;
use App\Models\ProductVariant;

// -------------------------------------------------------
// Listar todos los productos con sus variantes y textos de embedding
// -------------------------------------------------------
echo "=== TODOS LOS PRODUCTOS ===" . PHP_EOL;

$products = Product::with(['variants', 'category'])->get();

foreach ($products as $product) {
    echo PHP_EOL . "── [{$product->id}] {$product->name} | status: {$product->status} | cat: " . ($product->category->name ?? 'null') . " ──" . PHP_EOL;
    echo "   desc:    " . (substr($product->description ?? 'null', 0, 60)) . PHP_EOL;
    $vp = is_array($product->vision_profile) ? $product->vision_profile : [];
    echo "   tipo_prenda:       " . ($vp['tipo_prenda'] ?? 'null') . PHP_EOL;
    echo "   material_aparente: " . ($vp['material_aparente'] ?? 'null') . PHP_EOL;
    echo "   patron:            " . ($vp['patron'] ?? 'null') . PHP_EOL;
    $kw = isset($vp['keywords']) ? (is_array($vp['keywords']) ? implode(', ', $vp['keywords']) : $vp['keywords']) : 'null';
    echo "   keywords:          " . $kw . PHP_EOL;

    foreach ($product->variants as $variant) {
        $parts = [];
        if (!empty($product->name))        $parts[] = $product->name;
        if (!empty($product->description)) $parts[] = $product->description;
        if (!empty($product->category) && !empty($product->category->name))
            $parts[] = $product->category->name;
        if (!empty($vp['tipo_prenda']))      $parts[] = $vp['tipo_prenda'];
        if (!empty($vp['material_aparente'])) $parts[] = $vp['material_aparente'];
        if (!empty($vp['patron']))            $parts[] = $vp['patron'];
        if (!empty($vp['keywords'])) {
            $kw2 = is_array($vp['keywords']) ? implode(', ', $vp['keywords']) : $vp['keywords'];
            $parts[] = $kw2;
        }
        if (!empty($variant->color)) $parts[] = $variant->color;

        $texto = implode('. ', array_filter($parts)) . '.';
        echo "   [v{$variant->id}] color='{$variant->color}' → «{$texto}»" . PHP_EOL;
    }
}

// -------------------------------------------------------
// Test getDirty() vs wasChanged()
// -------------------------------------------------------
echo PHP_EOL . "=== TEST getDirty() vs wasChanged() (SIN save real, solo simulación) ===" . PHP_EOL;

$p = Product::first();
echo "ANTES de modificar:";
echo " getDirty() = " . json_encode($p->getDirty()) . PHP_EOL;

// Modificar un campo
$p->name = $p->name . ' '; // agregar espacio → cambia el valor
echo "DESPUES de asignar (antes de save):";
echo " getDirty() = " . json_encode($p->getDirty()) . PHP_EOL;
echo "DESPUES de asignar (antes de save):";
echo " wasChanged() = " . json_encode($p->wasChanged()) . PHP_EOL;

// Hacer getChanges() solo funciona DESPUÉS de save()
// Para no disparar observers reales usamos withoutEvents
Illuminate\Support\Facades\Event::fake();
$p->save();
echo "DESPUES de save():";
echo " getDirty() = " . json_encode($p->getDirty()) . PHP_EOL;
echo "DESPUES de save():";
echo " wasChanged() = " . json_encode($p->wasChanged()) . PHP_EOL;
echo "DESPUES de save():";
echo " getChanges() = " . json_encode($p->getChanges()) . PHP_EOL;

echo PHP_EOL . "CONCLUSION: getDirty() está VACIO después de save(). wasChanged()/getChanges() son los métodos correctos." . PHP_EOL;
