<?php

use App\Models\Product;
use App\Support\FormateadorCatalogoProductos;
use Illuminate\Contracts\Console\Kernel;

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$productos = Product::with('variants')->get();
$formateador = new FormateadorCatalogoProductos;
$catalogoTexto = $formateador->formatearCatalogo($productos);
echo 'Largo del catálogo: '.strlen($catalogoTexto)." caracteres\n";
echo 'Tokens estimados: '.(strlen($catalogoTexto) / 4)."\n";
