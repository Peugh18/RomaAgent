<?php
$msg = DB::table('messages')->whereRaw("JSON_EXTRACT(metadata, '$.type') = 'image'")->first();
$url = json_decode($msg->metadata, true)['image_url'] ?? null;
if ($url) {
    echo "URL: " . $url . "\n";
    $status = Http::get($url)->status();
    echo "Status: " . $status . "\n";
}
