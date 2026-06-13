<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use App\Actions\Pedidos\ConfirmarPagoPedido;
use App\Enums\SaleTransitionType;
use App\Models\CompanySetting;
use App\Models\Sale;
use App\Models\User;
use App\Support\PlantillaMensajePedido;
use Illuminate\Contracts\Console\Kernel;

$sale = Sale::where('phone_number', '51935561361')->latest()->first();
echo 'Status before: '.$sale->status->value."\n";

try {
    $confirmarPago = app(ConfirmarPagoPedido::class);
    $user = User::first();

    $mensaje = PlantillaMensajePedido::preview(
        $sale,
        SaleTransitionType::ConfirmPayment,
        CompanySetting::first()
    );

    // Test the whole confirmation process
    $sale = $confirmarPago->handle($sale, $user, $mensaje);

    echo 'Status after: '.$sale->status->value."\n";
    echo "SUCCESS!\n";
} catch (Exception $e) {
    echo 'Error: '.$e->getMessage()."\n";
    echo 'Class: '.get_class($e)."\n";
    echo 'Trace: '.$e->getTraceAsString()."\n";
}
