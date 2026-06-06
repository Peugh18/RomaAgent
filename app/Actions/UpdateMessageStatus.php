<?php

namespace App\Actions;

use App\Models\Message;
use App\Support\MessageBroadcaster;

class UpdateMessageStatus
{
    public function execute(array $payload): ?Message
    {
        $waId = $payload['wa_id'] ?? $payload['message_id'] ?? $payload['id'] ?? null;
        $status = $payload['status'] ?? null;

        if (! $waId || ! $status) {
            return null;
        }

        $message = Message::query()->where('message_id', $waId)->first();

        if (! $message) {
            return null;
        }

        $metadata = is_array($message->metadata) ? $message->metadata : [];
        $history = $metadata['status_history'] ?? [];
        $history[] = ['status' => $status, 'timestamp' => now()->toDateTimeString()];

        $message->update([
            'status' => $status,
            'metadata' => array_merge($metadata, ['status_history' => $history]),
        ]);

        MessageBroadcaster::broadcast($message, 'UpdateMessageStatus');

        return $message;
    }
}
