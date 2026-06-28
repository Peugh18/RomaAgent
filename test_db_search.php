<?php

use App\Models\Product;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$products = Product::all();
foreach ($products as $p) {
    $json = json_encode($p->vision_profile);
    if (stripos($json, 'ondas') !== false || stripos($json, 'zigzag') !== false) {
        echo $p->name.' - '.$p->id.PHP_EOL;
        echo $json.PHP_EOL.PHP_EOL;
    }
}
