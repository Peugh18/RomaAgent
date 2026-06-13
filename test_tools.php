<?php

use App\Services\Agente\EjecutorHerramientasAgente;
use Illuminate\Contracts\Console\Kernel;

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
$herramientas = app(EjecutorHerramientasAgente::class)->definiciones();
print_r($herramientas);
