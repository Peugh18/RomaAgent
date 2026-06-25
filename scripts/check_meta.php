<?php
$msg = DB::table('messages')
    ->whereRaw("JSON_EXTRACT(metadata, '$.type') = 'image' OR JSON_EXTRACT(metadata, '$.media_type') = 'image' OR JSON_EXTRACT(metadata, '$.media_path') IS NOT NULL")
    ->first();
echo "Metadata: " . $msg->metadata . "\n";
