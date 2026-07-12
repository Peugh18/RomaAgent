<?php

use App\Models\Message;
use Illuminate\Contracts\Console\Kernel;

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$msgs = Message::where('direction', 'incoming')->get();
foreach ($msgs as $m) {
    $raw = $m->metadata['whatsapp_raw'] ?? [];
    $type = $raw['type'] ?? '';
    if ($type === 'order' || $type === 'interactive' || $type === 'button') {
        echo json_encode(['id' => $m->id, 'type' => $type, 'raw' => $raw], JSON_PRETTY_PRINT)."\n";
    }
}
