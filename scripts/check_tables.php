<?php
echo "vision_analysis_cache count: " . DB::table('vision_analysis_cache')->count() . "\n";
echo "vision_learning_feedback count: " . DB::table('vision_learning_feedback')->count() . "\n";
echo "logs_ia count: " . DB::table('logs_ia')->where('action', 'like', '%vision%')->count() . "\n";
$first = DB::table('vision_analysis_cache')->first();
echo "vision_analysis_cache sample: " . json_encode($first) . "\n";
