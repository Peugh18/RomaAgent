<?php

use Illuminate\Support\Facades\DB;
use App\Services\Media\ImageAnalyzer;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;

// Bootstrapped by tinker

function getGeminiJudgeResponse(string $mediaPath, array $top10, string $apiKey): ?array {
    $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-lite:generateContent';
    
    // Prepare image
    $absPath = Storage::disk('public')->path($mediaPath);
    if (!file_exists($absPath)) {
        $absPath = storage_path('app/' . $mediaPath);
        if (!file_exists($absPath)) return null;
    }
    
    $mime = mime_content_type($absPath);
    $data = base64_encode(file_get_contents($absPath));
    
    $productsText = "Candidates:\n";
    foreach ($top10 as $index => $item) {
        $id = $item['id_producto'] ?? 'N/A';
        $name = $item['nombre_vestido'] ?? 'N/A';
        $color = $item['color'] ?? 'N/A';
        $sim = $item['similitud'] ?? 'N/A';
        $productsText .= "- Rank " . ($index + 1) . ": ID {$id} | Product: {$name} | Color: {$color} | Similarity: {$sim}\n";
    }

$prompt = <<<EOT
You are an expert fashion retail judge.
I am giving you an image sent by a customer, and a list of top 10 product variants retrieved by our vector search engine.
Your task is to evaluate the Top-1 result (Rank 1), and then perform a Two-Stage re-ranking.

### Task 1: Evaluate Top-1 (Stage 1)
Classify the Rank 1 result into EXACTLY ONE of these categories:
- EXACT_MATCH: Same exact model and same color.
- MODEL_MATCH: Same exact model, but different color.
- SIMILAR_MATCH: Not the same model, but a visually similar and commercially valid alternative.
- FAILURE: Incorrect product or completely unhelpful match.

If FAILURE, choose one reason:
- POOR_VISION_PROFILE: The system's extracted text description was poor.
- LOW_QUALITY_IMAGE: The customer's image is too blurry, dark, or cropped.
- VECTOR_RANKING: The image was clear, but the ranking algorithm returned a bad result.
- PRODUCT_NOT_IN_CATALOG: The customer's garment does not exist in our catalog.

### Task 2: Two-Stage Re-ranking
Look at all 10 candidates. Based ONLY on visual similarity to the customer's image, pick the absolute best matching candidate.
Classify this new best match into the same categories (EXACT_MATCH, MODEL_MATCH, SIMILAR_MATCH, FAILURE).

Return your response ONLY as valid JSON matching this schema:
{
  "stage1": {
    "variant_id": (int),
    "classification": "EXACT_MATCH|MODEL_MATCH|SIMILAR_MATCH|FAILURE",
    "failure_reason": "POOR_VISION_PROFILE|LOW_QUALITY_IMAGE|VECTOR_RANKING|PRODUCT_NOT_IN_CATALOG|null",
    "confused_product": "If wrong product, what product did it return? (string)|null",
    "confused_color": "If MODEL_MATCH, what color did it return vs expected? (e.g. 'Red instead of Blue')|null",
    "reason": "Brief explanation"
  },
  "stage2": {
    "variant_id": (int),
    "classification": "EXACT_MATCH|MODEL_MATCH|SIMILAR_MATCH|FAILURE",
    "reason": "Brief explanation"
  }
}
EOT;

    $payload = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt],
                    ['inlineData' => ['mimeType' => $mime, 'data' => $data]],
                    ['text' => $productsText]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.1,
            'responseMimeType' => 'application/json'
        ]
    ];

    $response = Http::withHeaders(['Content-Type' => 'application/json'])
        ->timeout(60)
        ->post($endpoint.'?key='.$apiKey, $payload);
        
    if ($response->successful()) {
        $text = $response->json('candidates.0.content.parts.0.text');
        if ($text) {
            $text = preg_replace('/```json/i', '', $text);
            $text = str_replace('```', '', $text);
            $text = trim($text);
            $decoded = json_decode($text, true);
            if (!$decoded) {
                echo "JSON Decode failed. Text was:\n" . substr($text, 0, 200) . "\n";
            }
            return $decoded;
        } else {
            echo "Response successful but text is empty. Body: " . $response->body() . "\n";
        }
    } else {
        echo "API Error: " . $response->status() . " - " . $response->body() . "\n";
    }
    
    return null;
}

$apiKey = env('GEMINI_API_KEY');
if (!$apiKey) {
    die("No Gemini API key found.\n");
}

$analyzer = app(ImageAnalyzer::class);

// Retrieve exactly 100 useful images
$messages = DB::table('messages')
    ->whereRaw("JSON_EXTRACT(metadata, '$.type') = 'image' AND metadata LIKE '%inbound-media%'")
    ->orderBy('id', 'desc')
    ->limit(300)
    ->get();

$progressFile = storage_path('app/audit_progress.json');
$progress = file_exists($progressFile) ? json_decode(file_get_contents($progressFile), true) : [];

$validCount = 0;
$maxCount = 100;

foreach ($messages as $msg) {
    if ($validCount >= $maxCount) break;
    
    if (isset($progress[$msg->id])) {
        $validCount++;
        continue;
    }
    
    $meta = json_decode($msg->metadata, true) ?? [];
    
    // Skip vouchers
    $content = strtolower($msg->content);
    if (str_contains($content, 'comprobante') || isset($meta['analysis']['is_voucher']) && $meta['analysis']['is_voucher']) {
        continue;
    }
    
    $mediaPath = $meta['local_url'] ?? $meta['image_url'] ?? null;
    if (!$mediaPath || !str_contains($mediaPath, 'inbound-media')) continue;
    
    $absPath = storage_path('app/public/inbound-media/' . basename($mediaPath));
    if (!file_exists($absPath)) {
        continue;
    }
    
    echo "Processing Message ID: {$msg->id} ({$validCount}/{$maxCount})...\n";
    
    try {
        // Run Stage 1 (Vector Search)
        // Pass a dummy URL that contains /storage/ so CargadorBytesMedia loads the local file
        $dummyUrl = 'https://localhost/storage/inbound-media/' . basename($mediaPath);
        $analysisResult = $analyzer->analyzeUrl($dummyUrl, ['caption_cliente' => $msg->content]);
        
        $top10 = $analysisResult['inbound_profile']['matches'] ?? [];
        if (empty($top10)) {
            echo "No products found for image.\n";
            continue;
        }
        
        $top10Limited = array_slice($top10, 0, 10);
        
        // Run AI Judge (Stage 1 eval + Stage 2 re-rank)
        $judgeResponse = getGeminiJudgeResponse($mediaPath, $top10Limited, $apiKey);
        
        if ($judgeResponse) {
            $progress[$msg->id] = [
                'msg_id' => $msg->id,
                'media_path' => $mediaPath,
                'top10' => $top10Limited,
                'judge' => $judgeResponse
            ];
            file_put_contents($progressFile, json_encode($progress, JSON_PRETTY_PRINT));
            $validCount++;
        } else {
            echo "Failed to get judge response.\n";
        }
    } catch (\Exception $e) {
        echo "Error on {$msg->id}: " . $e->getMessage() . "\n";
    }
    
    // Slight delay to avoid rate limits
    sleep(1);
}

echo "Finished collecting data. Analyzing results...\n";

// --- Compile metrics ---
$results = [
    'stage1' => [
        'EXACT_MATCH' => 0,
        'MODEL_MATCH' => 0,
        'SIMILAR_MATCH' => 0,
        'FAILURE' => 0
    ],
    'stage2' => [
        'EXACT_MATCH' => 0,
        'MODEL_MATCH' => 0,
        'SIMILAR_MATCH' => 0,
        'FAILURE' => 0
    ],
    'failure_reasons' => [],
    'confused_products' => [],
    'confused_colors' => []
];

$similarities = [];
$falsePositives = [];

foreach ($progress as $id => $data) {
    if (!isset($data['judge']['stage1'])) continue;
    
    $s1_class = $data['judge']['stage1']['classification'] ?? 'FAILURE';
    $s2_class = $data['judge']['stage2']['classification'] ?? 'FAILURE';
    
    $results['stage1'][$s1_class] = ($results['stage1'][$s1_class] ?? 0) + 1;
    $results['stage2'][$s2_class] = ($results['stage2'][$s2_class] ?? 0) + 1;
    
    if (!empty($data['top10'])) {
        $similarities[] = $data['top10'][0]['similitud'] ?? 0;
    }
    
    if ($s1_class === 'FAILURE') {
        $reason = $data['judge']['stage1']['failure_reason'] ?? 'UNKNOWN';
        $results['failure_reasons'][$reason] = ($results['failure_reasons'][$reason] ?? 0) + 1;
        
        $confusedProd = $data['judge']['stage1']['confused_product'] ?? null;
        if ($confusedProd && $confusedProd !== 'null') {
            $results['confused_products'][$confusedProd] = ($results['confused_products'][$confusedProd] ?? 0) + 1;
        }
    }
    
    if ($s1_class === 'MODEL_MATCH') {
        $confusedColor = $data['judge']['stage1']['confused_color'] ?? null;
        if ($confusedColor && $confusedColor !== 'null') {
            $results['confused_colors'][$confusedColor] = ($results['confused_colors'][$confusedColor] ?? 0) + 1;
        }
    }
    
    if ($s1_class === 'FAILURE' && count($falsePositives) < 20) {
        $falsePositives[] = [
            'media_path' => $data['media_path'],
            'top1' => $data['top10'][0] ?? null,
            'top3' => array_slice($data['top10'], 0, 3),
            'reason' => $data['judge']['stage1']['reason']
        ];
    }
}

arsort($results['confused_products']);
$results['confused_products'] = array_slice($results['confused_products'], 0, 10);

arsort($results['confused_colors']);
$results['confused_colors'] = array_slice($results['confused_colors'], 0, 10);

sort($similarities);
$count = count($similarities);
$metrics = [
    'avg_similarity' => $count > 0 ? array_sum($similarities) / $count : 0,
    'median_similarity' => $count > 0 ? $similarities[(int)($count/2)] : 0,
    'p90_similarity' => $count > 0 ? $similarities[(int)($count * 0.9)] : 0,
    'p95_similarity' => $count > 0 ? $similarities[(int)($count * 0.95)] : 0,
];

file_put_contents(storage_path('app/audit_final_metrics.json'), json_encode([
    'results' => $results,
    'metrics' => $metrics,
    'false_positives' => $falsePositives,
    'total' => $count
], JSON_PRETTY_PRINT));

echo "Audit complete! Results saved to storage/app/audit_final_metrics.json\n";
