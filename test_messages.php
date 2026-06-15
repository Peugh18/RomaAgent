<?php
use App\Models\Message;
$messages = Message::where('phone_number', '51943117190')->latest()->limit(15)->get()->reverse();
foreach ($messages as $m) {
    echo "[" . $m->created_at . "] " . $m->direction . ": " . $m->content . "\n";
}
