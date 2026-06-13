<?php

use App\Services\ConfiguracionEmpresa;
use App\Services\ContextoConversacion;
use Illuminate\Contracts\Console\Kernel;

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
$prompt = (new ContextoConversacion(new ConfiguracionEmpresa))->construirPromptParaAgente();
file_put_contents('prompt_dump.txt', $prompt);
