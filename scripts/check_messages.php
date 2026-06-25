<?php
echo "messages count: " . DB::table('messages')->count() . "\n";
echo "messages with media: " . DB::table('messages')->whereNotNull('media_url')->count() . "\n";
