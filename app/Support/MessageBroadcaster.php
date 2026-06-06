<?php

namespace App\Support;

use App\Events\MessageReceived;
use App\Models\Message;
use Illuminate\Support\Facades\Log;

class MessageBroadcaster
{
    public static function broadcast(Message $message, ?string $context = null): void
    {
        if (config('broadcasting.default') !== 'pusher') {
            return;
        }

        try {
            broadcast(new MessageReceived($message))->toOthers();
        } catch (\Throwable $e) {
            Log::error($context ? "{$context}: broadcast failed" : 'Broadcast failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
