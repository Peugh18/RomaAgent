<?php

use App\Models\Product;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$products = Product::all();
foreach ($products as $p) {
    echo $p->id.' - '.$p->name.PHP_EOL;
    echo json_encode($p->vision_profile, JSON_UNESCAPED_UNICODE).PHP_EOL.PHP_EOL;
}
