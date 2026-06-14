<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$desc = 'Vestido de mujer, patrón zig-zag, color rojo';
$emb = app(\App\Services\Vision\ProductEmbeddingService::class)->generarEmbeddingTexto($desc);
foreach(\App\Models\ProductVariant::all() as $v) {
    $sim = 0;
    $norm1 = 0;
    $norm2 = 0;
    for($i=0; $i<count($emb); $i++) {
        $sim += $emb[$i] * $v->image_embedding[$i];
        $norm1 += pow($emb[$i], 2);
        $norm2 += pow($v->image_embedding[$i], 2);
    }
    $sim = $sim / (sqrt($norm1) * sqrt($norm2));
    echo $v->product->name . ' - ' . $v->color . ' => ' . $sim . "\n";
}
