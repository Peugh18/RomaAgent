<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$msg = \App\Models\Message::where('direction', 'incoming')->whereNotNull('metadata->local_url')->latest()->first();
if (!$msg) { die("No image msg found"); }

$url = $msg->metadata['local_url'] ?? null;
if (!$url) { die("No local url"); }

// Use absolute path
$path = public_path($url);
echo "Image Path: $path\n";

$embService = app(\App\Services\Vision\ProductEmbeddingService::class);
$emb = $embService->generarEmbeddingImagen(url($url));

if (!$emb) { die("Could not generate embedding"); }

function cosineSimilarity(array $vec1, array $vec2): float {
    $dotProduct = 0.0;
    $norm1 = 0.0;
    $norm2 = 0.0;
    $count = min(count($vec1), count($vec2));
    for ($i = 0; $i < $count; $i++) {
        $v1 = (float) $vec1[$i];
        $v2 = (float) $vec2[$i];
        $dotProduct += $v1 * $v2;
        $norm1 += $v1 * $v1;
        $norm2 += $v2 * $v2;
    }
    if ($norm1 == 0.0 || $norm2 == 0.0) return 0.0;
    return $dotProduct / (sqrt($norm1) * sqrt($norm2));
}

foreach(\App\Models\ProductVariant::whereNotNull('image_embedding')->get() as $v) { 
    $sim = cosineSimilarity($emb, $v->image_embedding);
    echo $v->id . ' | ' . $v->product->name . ' | ' . $v->color . ' => Similarity: ' . $sim . "\n"; 
}
