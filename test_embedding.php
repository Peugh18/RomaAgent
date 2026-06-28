<?php

use App\Models\AgenteConfig;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Http;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$apiKey = AgenteConfig::first()->obtenerApiKey();

// Test text-embedding-004
$endpoint1 = 'https://generativelanguage.googleapis.com/v1beta/models/text-embedding-004:embedContent';
$payload = ['content' => ['parts' => [['text' => 'Hola']]]];
$response1 = Http::withHeaders(['Content-Type' => 'application/json'])->post($endpoint1.'?key='.$apiKey, $payload);
echo "text-embedding-004:\n";
print_r($response1->json());

// Test gemini-embedding-2 (current)
$endpoint2 = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-embedding-2:embedContent';
$response2 = Http::withHeaders(['Content-Type' => 'application/json'])->post($endpoint2.'?key='.$apiKey, $payload);
echo "\ngemini-embedding-2:\n";
print_r($response2->json());
