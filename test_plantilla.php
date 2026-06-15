<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use App\Models\Sale;
use App\Support\PlantillaMensajePedido;
use Illuminate\Contracts\Console\Kernel;

$sale = new Sale;
$sale->product_name = 'Vestido de Noche';
$sale->color = 'Rojo';
$sale->total_amount = 250.50;
$sale->delivery_district = 'Miraflores';
// Sin payment method definido
$sale->payment_method = null;
$sale->customer_data = [
    'nombre_completo' => 'María García (Completo)',
    'nombre' => 'María',
    'name' => 'María G',
];

$plantilla = 'Listo {nombre}. Tu pedido de {producto} ({color}) ha sido confirmado. Pago de S/ {total} mediante {metodo_pago}. Distrito: {distrito}.';
echo PlantillaMensajePedido::render($plantilla, $sale)."\n";
