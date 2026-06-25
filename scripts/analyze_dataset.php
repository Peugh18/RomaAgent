<?php
use Illuminate\Support\Facades\DB;

$totalImages = DB::table('messages')
    ->whereRaw("JSON_EXTRACT(metadata, '$.type') = 'image' OR JSON_EXTRACT(metadata, '$.media_type') = 'image' OR content LIKE '%[Imagen]%'")
    ->count();

$messages = DB::table('messages')
    ->whereRaw("JSON_EXTRACT(metadata, '$.type') = 'image' OR JSON_EXTRACT(metadata, '$.media_type') = 'image' OR JSON_EXTRACT(metadata, '$.media_path') IS NOT NULL")
    ->get(['id', 'metadata', 'content', 'created_at']);

$stats = [
    'total_historical' => count($messages),
    'vouchers' => 0,
    'prendas' => 0,
    'baja_calidad' => 0,
    'repetidas' => 0,
    'unknown' => 0
];

$hashes = [];

foreach ($messages as $msg) {
    $meta = json_decode($msg->metadata, true) ?? [];
    
    // Check for voucher/comprobante
    $content = strtolower($msg->content);
    if (str_contains($content, 'comprobante') || str_contains($content, 'yape') || str_contains($content, 'plin') || str_contains($content, 'pago') || str_contains($content, 'transferencia')) {
        $stats['vouchers']++;
        continue;
    }
    
    if (isset($meta['analysis']['is_voucher']) && $meta['analysis']['is_voucher']) {
        $stats['vouchers']++;
        continue;
    }
    
    // Check if it's a product/prenda
    if (isset($meta['vision_profile']) || isset($meta['analysis']['product_name']) || isset($meta['analysis']['intent']) && $meta['analysis']['intent'] === 'search_image') {
        $stats['prendas']++;
    } else {
        $stats['unknown']++;
    }
}

echo "Dataset Report:\n";
echo json_encode($stats, JSON_PRETTY_PRINT) . "\n";
