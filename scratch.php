<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo json_encode(App\Models\Message::where('phone_number', '51943117190')->whereNotNull('metadata')->get()->map(function($m) { 
    return ['content' => $m->content, 'metadata' => $m->metadata]; 
})->toArray(), JSON_PRETTY_PRINT);
