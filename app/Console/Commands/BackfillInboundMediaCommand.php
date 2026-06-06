<?php

namespace App\Console\Commands;

use App\Models\Message;
use App\Services\ServicioResolucionMediaEntrante;
use Illuminate\Console\Command;

class BackfillInboundMediaCommand extends Command
{
    protected $signature = 'media:backfill-inbound {--phone=}';

    protected $description = 'Descarga media faltante (audio, sticker, etc.) vía roma-api';

    public function handle(ServicioResolucionMediaEntrante $resolver): int
    {
        $query = Message::query()
            ->where('direction', 'incoming')
            ->orderByDesc('id');

        if ($phone = $this->option('phone')) {
            $query->where('phone_number', $phone);
        }

        $updated = 0;

        foreach ($query->cursor() as $message) {
            $meta = is_array($message->metadata) ? $message->metadata : [];

            if (! empty($meta['local_url'])) {
                continue;
            }

            $raw = $meta['whatsapp_raw'] ?? null;
            $remote = $meta['media_url'] ?? $meta['image_url'] ?? null;

            $payload = [
                'message_type' => $meta['whatsapp_message_type'] ?? $meta['type'] ?? 'text',
                'media_url' => $meta['media_url'] ?? null,
                'image_url' => $meta['image_url'] ?? null,
                'mime_type' => $meta['mime_type'] ?? null,
                'raw' => is_array($raw) ? $raw : null,
            ];

            $type = $resolver->inferirTipo($payload);
            if (! in_array($type, ['image', 'audio', 'video', 'sticker', 'document'], true)) {
                continue;
            }

            if (! is_array($raw) && ! is_string($remote)) {
                continue;
            }

            $resolved = $resolver->resolver($payload, $type, $message->message_id);
            if ($resolved === null || empty($resolved['local_url'])) {
                $this->warn("Sin media local: {$message->message_id} ({$type})");

                continue;
            }

            $message->update([
                'metadata' => $resolver->aplicarAMetadata($meta, $resolved, $type),
            ]);

            $updated++;
            $this->info("OK {$message->message_id} → {$type}");
        }

        $this->info("Actualizados: {$updated}");

        return self::SUCCESS;
    }
}
