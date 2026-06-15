<?php

use App\Actions\GenerarRespuestaAgente;
use App\Models\Message;
use Illuminate\Contracts\Console\Kernel;

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$msg = Message::where('direction', 'incoming')->latest()->first();

echo "Mensaje usar: {$msg->id}\n";
$action = app(GenerarRespuestaAgente::class);
$action->ejecutar($msg);
echo "Finalizado\n";
