<?php

use App\Models\Customer;
use App\Models\Message;
use App\Services\Agente\EjecutorHerramientasAgente;
use App\Services\ClienteGemini;
use App\Services\ConfiguracionAgente;
use App\Services\ContextoConversacion;
use Illuminate\Contracts\Console\Kernel;

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$msg = Message::where('direction', 'incoming')->latest()->first();

$config = new ConfiguracionAgente;
$contexto = app(ContextoConversacion::class);
$cliente = new ClienteGemini($config->obtenerApiKey(), 'gemini-2.5-flash', 0.7);

$customer = Customer::resolverDesdeMensaje($msg->phone_number, $msg->customer_name);
$promptCompleto = $contexto->construirPromptParaAgenteConPedido($customer);

$historial = [
    ['role' => 'user', 'content' => $msg->content],
];

$herramientas = app(EjecutorHerramientasAgente::class)->definiciones();

$res = $cliente->generarConHerramientas(
    $promptCompleto,
    $historial,
    $herramientas,
    function ($n, $a) {
        return new stdClass;
    }
);
var_dump($res);

$error = $cliente->obtenerUltimoError();
if ($error) {
    print_r($error);
}
