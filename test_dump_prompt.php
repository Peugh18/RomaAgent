<?php

use App\Models\Customer;
use App\Models\Message;
use App\Services\ContextoConversacion;
use Illuminate\Contracts\Console\Kernel;

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$msg = Message::where('direction', 'incoming')->latest()->first();
$contexto = app(ContextoConversacion::class);
$customer = Customer::resolverDesdeMensaje($msg->phone_number, $msg->customer_name);
$promptCompleto = $contexto->construirPromptParaAgenteConPedido($customer);

file_put_contents('test_prompt_full.txt', $promptCompleto);
echo 'Length: '.strlen($promptCompleto)."\n";
