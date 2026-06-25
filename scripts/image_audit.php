<?php
// scripts/image_audit.php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use App\Models\ProductVariant;
use App\Models\Message;
use Illuminate\Support\Facades\DB;

// 1. Variant counts
$totalVariants = ProductVariant::count();
$withEmbedding = ProductVariant::whereNotNull('image_embedding')->count();
$withoutEmbedding = $totalVariants - $withEmbedding;

// 2. Vision profile completeness (assuming JSON column 'vision_profile' with key 'descripcion_vectorial')
$withVision = ProductVariant::whereNotNull('vision_profile')->whereRaw("JSON_EXTRACT(vision_profile, '$.descripcion_vectorial') IS NOT NULL")->count();

// 3. Example matches (latest 20 messages with successful product match)
$examples = Message::whereNotNull('metadata->vision->inbound_profile->encontrado')
    ->where('metadata->vision->inbound_profile->encontrado', true)
    ->orderByDesc('created_at')
    ->take(20)
    ->get()
    ->map(function($msg){
        $profile = $msg->metadata['vision']['inbound_profile'];
        return [
            'description' => $profile['descripcion_vectorial'] ?? null,
            'product_id' => $profile['matches'][0][0]['id_producto'] ?? null,
            'similarity' => $profile['matches'][0][0]['similitud'] ?? null,
        ];
    })
    ->filter(fn($item)=> $item['description'] !== null)
    ->values()
    ->toArray();

// 4. Worst and best cases based on similarity scores
$allScores = Message::whereNotNull('metadata->vision->inbound_profile->matches')
    ->pluck('metadata->vision->inbound_profile->matches')
    ->flatten(2)
    ->pluck('similitud')
    ->filter()
    ->values()
    ->toArray();

usort($allScores, fn($a,$b)=> $a<=>$b);
$minScores = array_slice($allScores, 0, 10);
$maxScores = array_slice($allScores, -10, 10);

$distribution = [
    'average' => empty($allScores) ? null : array_sum($allScores)/count($allScores),
    'median' => (function($arr){$c=count($arr); if($c===0) return null; sort($arr); $mid=intval($c/2); return $c%2===0 ? ($arr[$mid-1]+$arr[$mid])/2 : $arr[$mid];})($allScores),
    'p90' => $c=count($allScores) ? $allScores[intval(0.9*$c)] : null,
    'p95' => $c=count($allScores) ? $allScores[intval(0.95*$c)] : null,
];

// 5. Fallback percentage (messages where no product found)
$totalMessages = Message::whereNotNull('metadata->vision')->count();
$fallbackCount = Message::whereNotNull('metadata->vision')
    ->where(function($q){
        $q->where('metadata->vision->inbound_profile->encontrado', false)
          ->orWhereNull('metadata->vision->inbound_profile->encontrado');
    })->count();
$fallbackPct = $totalMessages ? $fallbackCount / $totalMessages * 100 : null;

// 6. Duplicate descriptions in catalog
$dupCounts = ProductVariant::select(DB::raw('JSON_EXTRACT(vision_profile, "$.descripcion_vectorial") as desc'))
    ->whereNotNull('vision_profile')
    ->groupBy('desc')
    ->havingRaw('COUNT(*) > 1')
    ->count();

$result = [
    'total_variants' => $totalVariants,
    'with_embedding' => $withEmbedding,
    'without_embedding' => $withoutEmbedding,
    'with_vision' => $withVision,
    'examples' => $examples,
    'worst_scores' => $minScores,
    'best_scores' => $maxScores,
    'distribution' => $distribution,
    'fallback_percentage' => $fallbackPct,
    'duplicate_descriptions' => $dupCounts,
];

echo json_encode($result);
?>
