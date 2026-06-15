<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use App\Actions\Pedidos\ConfirmarPagoPedido;
use App\Models\Sale;
use App\Models\User;
use App\Services\Pedidos\ServicioStockPedido;
use Illuminate\Contracts\Console\Kernel;

$sale = Sale::where('phone_number', '51935561361')->latest()->first();

if (! $sale) {
    echo "No sale found.\n";
    exit;
}

echo 'Sale Status: '.$sale->status->value."\n";
echo 'Product: '.$sale->product_name."\n";
echo 'Variant ID: '.$sale->product_variant_id."\n";
echo 'Size: '.$sale->size."\n";
echo 'Quantity: '.$sale->quantity."\n";

try {
    $confirmarPago = app(ConfirmarPagoPedido::class);
    $user = User::first(); // Just get a user

    // Instead of actually confirming and sending a WhatsApp message,
    // let's just check if it would throw an error during decrementarPorVentaConfirmada
    $servicioStock = app(ServicioStockPedido::class);

    // Test stock service
    try {
        $servicioStock->decrementarPorVentaConfirmada(
            $sale->product_variant_id,
            $sale->size,
            $sale->quantity
        );
        echo "Stock validation passed!\n";
    } catch (Exception $e) {
        echo 'Stock Error: '.$e->getMessage()."\n";
    }

} catch (Exception $e) {
    echo 'Error: '.$e->getMessage()."\n";
}
