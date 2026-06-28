<?php

use App\Models\ProductVariant;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$variants = ProductVariant::with('product')->get();
foreach ($variants as $v) {
    if ($v->product->vision_profile) {
        $h = strtolower($v->product->vision_profile['huella_digital'] ?? '');
        if (strpos($h, 'raya') !== false || strpos($h, 'onda') !== false || strpos($h, 'azul') !== false) {
            echo $v->id.' -> '.$v->product->name.' -> '.$h."\n";
        }
    }
}
