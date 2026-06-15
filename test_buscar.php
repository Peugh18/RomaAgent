<?php

use App\Services\Agente\Tools\BuscarProductosTool;
use Illuminate\Contracts\Console\Kernel;

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

// Test general
echo "Test 1: General (vestido)\n";
$res1 = BuscarProductosTool::execute(['q' => 'vestido']);
foreach ($res1['items'] as $item) {
    echo '- '.$item['name']."\n";
}

echo "\nTest 2: Aurora\n";
$res2 = BuscarProductosTool::execute(['q' => 'Aurora']);
foreach ($res2['items'] as $item) {
    echo '- '.$item['name']."\n";
}
