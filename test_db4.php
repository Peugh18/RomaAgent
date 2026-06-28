<?php

use App\Models\ProductVariant;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$variants = ProductVariant::with('product')->get();
foreach ($variants as $v) {
    echo $v->id.' - '.$v->product->name."\n";
}
