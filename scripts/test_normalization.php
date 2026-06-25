<?php

use App\Models\ProductVariant;

require __DIR__.'/../vendor/autoload.php';

$variants = ProductVariant::with('product')->get();

$output = "# 32 Ejemplos de Normalización (Antes vs Después)\n\n";

foreach ($variants as $variant) {
    $output .= "### Variante ID: " . $variant->id . " | Producto: " . ($variant->product->name ?? 'N/A') . "\n";
    
    // OLD logic
    $product = $variant->product;
    $parts = [];
    if (!empty($product->name)) $parts[] = $product->name;
    if (!empty($product->description)) $parts[] = $product->description;
    if (!empty($product->category) && !empty($product->category->name)) $parts[] = $product->category->name;
    $vision = $product->vision_profile ?? [];
    if (!empty($vision['tipo_prenda'])) $parts[] = $vision['tipo_prenda'];
    if (!empty($vision['material_aparente'])) $parts[] = $vision['material_aparente'];
    if (!empty($vision['patron'])) $parts[] = $vision['patron'];
    if (!empty($vision['keywords'])) {
        $keywords = is_array($vision['keywords']) ? implode(', ', $vision['keywords']) : $vision['keywords'];
        $parts[] = $keywords;
    }
    if (!empty($variant->color)) $parts[] = $variant->color;
    $oldDesc = implode('. ', array_filter($parts)) . '.';
    
    $output .= "**ANTES:**\n> " . $oldDesc . "\n\n";
    
    // NEW logic
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
    
    if (!empty($variant->color)) $partes[] = "Color " . mb_strtolower($variant->color);
    if (!empty($vision['patron'])) $partes[] = "Diseño " . mb_strtolower($vision['patron']);
    if (!empty($vision['material_aparente'])) $partes[] = "Material " . mb_strtolower($vision['material_aparente']);
    
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
        if (!empty($desc)) $partes[] = $desc;
    }
    
    $descripcion = implode('. ', $partes) . '.';
    $descripcion = preg_replace('/\s+/', ' ', $descripcion);
    $descripcion = preg_replace('/\.{2,}/', '.', $descripcion);
    $descripcion = str_replace(' .', '.', $descripcion);
    $descripcion = trim($descripcion);
    
    $output .= "**DESPUÉS:**\n> " . $descripcion . "\n\n";
    $output .= "---\n\n";
}

file_put_contents(storage_path('app/ejemplos_normalizacion.md'), $output);
echo "Archivo generado: storage/app/ejemplos_normalizacion.md\n";
