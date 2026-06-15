<?php

use App\Models\LogIA;
use Illuminate\Contracts\Console\Kernel;

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
print_r(LogIA::latest()->first()->toArray());
