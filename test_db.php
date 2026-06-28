<?php

use App\Models\ProductVariant;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$v = ProductVariant::first();
echo "Vision Profile:\n";
print_r($v->product->vision_profile);
echo "\n---\nImage embedding:\n";
echo $v->image_embedding ? 'YES' : 'NO';
