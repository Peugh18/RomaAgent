<?php

namespace App\Support;

class ContratoMensajeWhatsapp
{
    public const VERSION = 1;

    public const SOURCE = 'laravel_agent';

    /**
     * @param  array<string, mixed>|null  $metadata
     * @return array<string, mixed>
     */
    public static function buildOutbound(
        string $phone,
        string $body,
        string $waId,
        ?string $imageUrl = null,
        ?array $metadata = null
    ): array {
        $metadata = $metadata ?? [];
        $type = $metadata['type'] ?? 'text';
        if ($imageUrl && $type === 'text') {
            $type = 'image';
        }

        $payload = [
            'to' => $phone,
            'type' => $type,
            'context' => [
                'source' => self::SOURCE,
                'message_id' => $waId,
            ],
            'roma_contract_version' => self::VERSION,
        ];

        if ($type === 'text') {
            $payload['text'] = ['body' => $body];
            $payload['wa_id'] = $waId;
            $payload['sender_phone'] = $phone;
            $payload['message_body'] = $body;
            $payload['direction'] = 'outbound';
        } elseif ($type === 'image') {
            $payload['image'] = [
                'link' => $imageUrl ?? ($metadata['image_url'] ?? ''),
                'caption' => $body ?: ($metadata['image_caption'] ?? ''),
            ];
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function inboundMetadata(array $payload): array
    {
        $messageType = $payload['message_type'] ?? 'text';
        $raw = is_array($payload['raw'] ?? null) ? $payload['raw'] : null;
        if ($raw && ! in_array($messageType, ['image', 'audio', 'video', 'sticker', 'document', 'location'], true)) {
            $rawType = (string) ($raw['type'] ?? '');
            if ($rawType === 'voice') {
                $messageType = 'audio';
            } elseif (in_array($rawType, ['image', 'audio', 'video', 'sticker', 'document', 'location'], true)) {
                $messageType = $rawType;
            }
        }

        $ubicacion = self::extraerUbicacion($payload);
        if ($ubicacion !== null) {
            $messageType = 'location';
        }

        $meta = [
            'roma_contract_version' => self::VERSION,
            'whatsapp_message_type' => $messageType,
        ];

        if (! empty($payload['interactive']) && is_array($payload['interactive'])) {
            $meta['interactive'] = $payload['interactive'];
        }

        if (! empty($payload['media_url']) && is_string($payload['media_url'])) {
            $meta['media_url'] = $payload['media_url'];
        }

        if (! empty($payload['mime_type']) && is_string($payload['mime_type'])) {
            $meta['mime_type'] = $payload['mime_type'];
        }

        if ($messageType === 'image' && ! empty($payload['image_url'])) {
            $meta['type'] = 'image';
            $meta['image_url'] = $payload['image_url'];
        }

        $mediaUrl = $payload['media_url'] ?? null;
        if (is_string($mediaUrl) && $mediaUrl !== '') {
            $meta['media_url'] = $mediaUrl;
        }

        if (in_array($messageType, ['audio', 'video', 'document', 'sticker'], true)) {
            $meta['type'] = $messageType;
        }

        if ($messageType === 'sticker' && ! empty($meta['media_url'])) {
            $meta['image_url'] = $meta['media_url'];
        }

        if ($ubicacion !== null) {
            $meta['type'] = 'location';
            $meta['latitude'] = $ubicacion['latitude'];
            $meta['longitude'] = $ubicacion['longitude'];
            $meta['maps_url'] = sprintf(
                'https://www.google.com/maps?q=%F,%F',
                $ubicacion['latitude'],
                $ubicacion['longitude'],
            );

            if ($ubicacion['name'] !== null) {
                $meta['location_name'] = $ubicacion['name'];
            }

            if ($ubicacion['address'] !== null) {
                $meta['location_address'] = $ubicacion['address'];
            }
        }

        if (! empty($payload['raw']) && is_array($payload['raw'])) {
            $meta['whatsapp_raw'] = $payload['raw'];
        }

        return $meta;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function inboundContent(array $payload): string
    {
        $raw = $payload['text'] ?? $payload['content'] ?? $payload['message_body'] ?? '';
        if (is_array($raw) && isset($raw['body'])) {
            $raw = $raw['body'];
        }

        $content = is_string($raw) ? trim($raw) : '';
        $messageType = $payload['message_type'] ?? 'text';
        $ubicacion = self::extraerUbicacion($payload);

        if ($ubicacion !== null) {
            $etiqueta = '📍 Ubicación compartida';

            if ($ubicacion['address'] !== null && $ubicacion['address'] !== '') {
                return $etiqueta.': '.$ubicacion['address'];
            }

            if ($ubicacion['name'] !== null && $ubicacion['name'] !== '') {
                return $etiqueta.': '.$ubicacion['name'];
            }

            return $etiqueta;
        }

        if ($messageType === 'order' || ($payload['interactive']['reply_type'] ?? '') === 'order') {
            $items = is_array($payload['interactive']['product_items'] ?? null) ? $payload['interactive']['product_items'] : [];
            $text = "🛒 [PEDIDO DESDE CATÁLOGO WHATSAPP]\n";
            foreach ($items as $item) {
                $q = $item['quantity'] ?? 1;
                $price = ($item['item_price'] ?? 0) / 100;
                $text .= "- {$q}x Producto/SKU: ".($item['product_retailer_id'] ?? 'Desconocido')."\n";
            }
            if (! empty($payload['interactive']['text'])) {
                $text .= "Mensaje adjunto: \"{$payload['interactive']['text']}\"\n";
            }

            return trim($text);
        }

        if (($payload['interactive']['reply_type'] ?? '') === 'product_reply') {
            return "👗 [CONSULTA DE PRODUCTO DESDE CATÁLOGO]\nProducto/SKU: ".($payload['interactive']['product_retailer_id'] ?? 'Desconocido');
        }

        if ($content === '' && ! empty($payload['interactive']['title'])) {
            return (string) $payload['interactive']['title'];
        }

        if ($messageType === 'image' && $content === '') {
            return '📷 Imagen';
        }

        if ($content === '' || $content === '[non-text]') {
            $raw = is_array($payload['raw'] ?? null) ? $payload['raw'] : [];
            $rawType = is_string($raw['type'] ?? null) ? $raw['type'] : $messageType;

            return match ($rawType) {
                'audio', 'voice' => '🎤 Audio',
                'video' => '🎬 Video',
                'document' => '📄 Documento',
                'sticker' => '🙂 Sticker',
                'image' => '📷 Imagen',
                'location' => '📍 Ubicación compartida',
                default => $content,
            };
        }

        return $content;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{latitude: float, longitude: float, name: ?string, address: ?string}|null
     */
    public static function extraerUbicacion(array $payload): ?array
    {
        $bloque = null;

        if (is_array($payload['location'] ?? null)) {
            $bloque = $payload['location'];
        } elseif (is_array($payload['raw']['location'] ?? null)) {
            $bloque = $payload['raw']['location'];
        }

        $latitud = $bloque['latitude'] ?? $payload['latitude'] ?? null;
        $longitud = $bloque['longitude'] ?? $payload['longitude'] ?? null;

        if (! is_numeric($latitud) || ! is_numeric($longitud)) {
            return null;
        }

        $nombre = $bloque['name'] ?? $payload['location_name'] ?? null;
        $direccion = $bloque['address'] ?? $payload['location_address'] ?? null;

        return [
            'latitude' => (float) $latitud,
            'longitude' => (float) $longitud,
            'name' => is_string($nombre) && trim($nombre) !== '' ? trim($nombre) : null,
            'address' => is_string($direccion) && trim($direccion) !== '' ? trim($direccion) : null,
        ];
    }
}
