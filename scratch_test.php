<?php

use App\Actions\Pedidos\ActualizarPedidoVenta;
use App\Models\Customer;
use App\Models\ZonaEnvio;
use App\Services\Agente\Tools\ConsultarCoberturaTool;

// 1. Ensure Zonas exist
if (ZonaEnvio::count() === 0) {
    ZonaEnvio::create([
        'departamento' => 'Lima',
        'provincia' => 'Lima',
        'distrito' => 'Los Olivos',
        'tipo_envio' => 'motorizado',
        'costo_referencial' => 15.00,
        'activo' => true,
    ]);
}

echo "=== Caso 1 & 4: Motorizado (Los Olivos) ===\n";
$coberturaMoto = ConsultarCoberturaTool::execute(['distrito' => 'Los Olivos']);
echo json_encode($coberturaMoto, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n\n";

echo "=== Caso 2: Shalom (Ayacucho) ===\n";
$coberturaShalom = ConsultarCoberturaTool::execute(['distrito' => 'Ayacucho']);
echo json_encode($coberturaShalom, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n\n";

echo "=== Caso 3 & 7: ActualizarPedido con múltiples y costo referencial ===\n";
$customer = Customer::firstOrCreate(['phone_number' => '51987654321'], ['name' => 'Testing User']);
$action = new ActualizarPedidoVenta;

$saleData = [
    'items' => [
        [
            'product_name' => 'Vestido Roma',
            'color' => 'Blanco',
            'quantity' => 2,
            'unit_price' => 120.00,
            'size' => 'Standard',
        ],
        [
            'product_name' => 'Vestido Kiara',
            'color' => 'Negro',
            'quantity' => 1,
            'unit_price' => 140.00,
            'size' => 'M',
        ],
    ],
    'customer_data' => [
        'nombre' => 'Test',
        'tipo_envio' => $coberturaMoto['tipo_envio'],
        'costo_referencial' => $coberturaMoto['costo_referencial'],
        'distrito' => $coberturaMoto['distrito'],
        'direccion' => 'Av. Test 123',
        'latitude' => -12.046374,
        'longitude' => -77.042793,
        'maps_url' => 'https://maps.google.com/?q=-12.046374,-77.042793',
    ],
];

$sale = $action->handle($customer, $saleData);

echo 'Total Sale: S/ '.$sale->total_amount."\n";
echo 'Customer Data guardado: '.json_encode($sale->customer_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";
echo "Items en la BD:\n";
foreach ($sale->items as $item) {
    echo "- {$item->quantity}x {$item->product_name} ({$item->color} - {$item->size}) @ S/ {$item->unit_price} \n";
}
echo "\n";
