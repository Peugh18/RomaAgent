<?php

namespace App\Actions\Mensajes;

use App\Jobs\SendWhatsappMessageJob;
use App\Models\Message;
use App\Services\ServicioMediaProducto;
use App\Support\MessageBroadcaster;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class ReenviarMensajeWhatsapp
{
    public function __construct(
        private ServicioMediaProducto $mediaProducto,
    ) {}

    public function handle(Message $message): Message
    {
        if ($message->direction !== 'outgoing') {
            throw new InvalidArgumentException('Solo se pueden reenviar mensajes salientes.');
        }

        if ($message->status !== 'failed') {
            throw new InvalidArgumentException('Solo se pueden reenviar mensajes con estado fallido.');
        }

        $metadata = is_array($message->metadata) ? $message->metadata : [];
        $type = (string) ($metadata['type'] ?? 'text');
        $imageUrl = isset($metadata['image_url']) && is_string($metadata['image_url'])
            ? $metadata['image_url']
            : null;

        if ($type === 'image' && $imageUrl !== null) {
            $imageUrl = $this->refrescarUrlPublica($imageUrl);

            if (! $this->mediaProducto->urlEsAccesibleParaWhatsapp($imageUrl)) {
                Log::warning('ReenviarMensajeWhatsapp: foto no accesible', [
                    'message_id' => $message->id,
                    'image_url' => $imageUrl,
                    'public_app_url' => config('app.public_url'),
                ]);

                throw new InvalidArgumentException(
                    'La foto no está disponible. Sube la imagen en Productos o verifica PUBLIC_APP_URL en .env.'
                );
            }

            $metadata['image_url'] = $imageUrl;
        }

        $historial = is_array($metadata['resend_history'] ?? null) ? $metadata['resend_history'] : [];
        $historial[] = [
            'at' => now()->toDateTimeString(),
            'previous_message_id' => $message->message_id,
            'previous_status' => $message->status,
        ];

        unset($metadata['send_error']);

        $message->update([
            'message_id' => 'out_'.uniqid(),
            'status' => 'pending',
            'metadata' => array_merge($metadata, [
                'resend_history' => $historial,
                'resent_at' => now()->toDateTimeString(),
            ]),
        ]);

        Log::info('ReenviarMensajeWhatsapp: reencolando', [
            'id' => $message->id,
            'phone' => $message->phone_number,
            'type' => $type,
        ]);

        MessageBroadcaster::broadcast($message->fresh(), 'ReenviarMensajeWhatsapp');
        SendWhatsappMessageJob::dispatchSync($message->fresh());

        return $message->fresh();
    }

    private function refrescarUrlPublica(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path) || ! str_contains($path, '/storage/')) {
            return $url;
        }

        $publicBase = rtrim((string) config('app.public_url', config('app.url')), '/');

        return $publicBase.$path;
    }
}
