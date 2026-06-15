<?php

use App\Services\ClienteGemini;
use App\Services\ConfiguracionAgente;
use Illuminate\Contracts\Console\Kernel;

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$config = new ConfiguracionAgente;
$apiKey = $config->obtenerApiKey();
echo 'API Key Length: '.strlen($apiKey)."\n";

$cliente = new ClienteGemini($apiKey, 'gemini-2.5-flash', 0.7);
$res = $cliente->generarRespuesta('Eres un asistente. Responde hola.', [['role' => 'user', 'content' => 'Hola']]);

echo "Respuesta: \n";
var_dump($res);

if (! $res) {
    echo "Error:\n";
    print_r($cliente->obtenerUltimoError());
}
