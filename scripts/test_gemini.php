<?php

use Illuminate\Support\Facades\Http;

require __DIR__.'/../vendor/autoload.php';
// Bootstrapped by tinker

$apiKey = env('GEMINI_API_KEY');
$endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent?key=' . $apiKey;

$payload = [
    'contents' => [
        [
            'parts' => [
                ['text' => 'Hello, return a simple JSON: {"test": true}']
            ]
        ]
    ],
    'generationConfig' => [
        'temperature' => 0.1,
        'responseMimeType' => 'application/json'
    ]
];

$response = Http::withHeaders(['Content-Type' => 'application/json'])
    ->post($endpoint, $payload);

echo "Status: " . $response->status() . "\n";
echo "Body: " . $response->body() . "\n";
