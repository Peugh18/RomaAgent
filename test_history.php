<?php

use App\Services\ContextoConversacion;
use Illuminate\Contracts\Console\Kernel;

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
$hist = app(ContextoConversacion::class)->obtenerHistorial('51959166911');
print_r($hist);
