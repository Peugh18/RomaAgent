<?php

namespace App\Support\Whatsapp;

use App\Support\ContratoMensajeWhatsapp;

class NormalizadorWebhookMeta
{
    /**
     * @param  array<string, mixed>  $message
     * @param  array<int, mixed>  $contacts
     * @return array<string, mixed>
     */
    public static function normalizarMensaje(array $message, string $metaPhoneId, array $contacts = []): array
    {
        $senderPhone = (string) ($message['from'] ?? '');
        $waId = (string) ($message['id'] ?? '');
        $timestamp = isset($message['timestamp']) ? (int) $message['timestamp'] : time();
        $type = (string) ($message['type'] ?? 'text');

        $eventType = 'text';
        $text = null;
        $interactive = null;

        if ($type === 'text') {
            $text = $message['text']['body'] ?? null;
            $eventType = 'text';
        } elseif ($type === 'image') {
            $text = $message['image']['caption'] ?? '📷 Imagen';
            $eventType = 'image';
        } elseif ($type === 'audio') {
            $text = '🎤 Audio';
            $eventType = 'audio';
        } elseif ($type === 'video') {
            $text = $message['video']['caption'] ?? '🎬 Video';
            $eventType = 'video';
        } elseif ($type === 'sticker') {
            $text = '🙂 Sticker';
            $eventType = 'sticker';
        } elseif ($type === 'document') {
            $text = $message['document']['caption'] ?? $message['document']['filename'] ?? '📄 Documento';
            $eventType = 'document';
        } elseif ($type === 'location') {
            $text = '📍 Ubicación compartida';
            $eventType = 'location';
        } elseif ($type === 'interactive') {
            $interactivePayload = is_array($message['interactive'] ?? null) ? $message['interactive'] : [];
            if (($interactivePayload['type'] ?? '') === 'button_reply') {
                $eventType = 'interactive_button_reply';
                $interactive = [
                    'reply_type' => 'button',
                    'id' => $interactivePayload['button_reply']['id'] ?? '',
                    'title' => $interactivePayload['button_reply']['title'] ?? '',
                ];
            } elseif (($interactivePayload['type'] ?? '') === 'list_reply') {
                $eventType = 'interactive_list_reply';
                $interactive = [
                    'reply_type' => 'list',
                    'id' => $interactivePayload['list_reply']['id'] ?? '',
                    'title' => $interactivePayload['list_reply']['title'] ?? '',
                ];
            } elseif (($interactivePayload['type'] ?? '') === 'product_reply') {
                $eventType = 'interactive_product_reply';
                $interactive = [
                    'reply_type' => 'product_reply',
                    'product_retailer_id' => $interactivePayload['product_reply']['product_retailer_id'] ?? '',
                ];
            }
        } elseif ($type === 'order') {
            $eventType = 'order';
            $orderPayload = is_array($message['order'] ?? null) ? $message['order'] : [];
            $interactive = [
                'reply_type' => 'order',
                'catalog_id' => $orderPayload['catalog_id'] ?? '',
                'text' => $orderPayload['text'] ?? '',
                'product_items' => $orderPayload['product_items'] ?? [],
            ];
        }

        $imageUrl = null;
        if ($type === 'image' || $type === 'sticker') {
            $block = is_array($message[$type] ?? null) ? $message[$type] : [];
            $imageUrl = $block['link'] ?? $block['url'] ?? null;
        }

        $profileName = null;
        foreach ($contacts as $contact) {
            if (is_array($contact) && ($contact['wa_id'] ?? '') === $senderPhone) {
                $profileName = $contact['profile']['name'] ?? null;
                break;
            }
        }

        return [
            'event' => 'message',
            'from' => $senderPhone,
            'wa_id' => $waId,
            'message_type' => $eventType,
            'text' => is_string($text) ? $text : null,
            'interactive' => $interactive,
            'image_url' => is_string($imageUrl) ? $imageUrl : null,
            'meta_phone_id' => $metaPhoneId,
            'profile_name' => is_string($profileName) ? $profileName : null,
            'timestamp' => date('c', $timestamp),
            'raw' => $message,
        ];
    }

    /**
     * @param  array<string, mixed>  $status
     * @return array<string, mixed>
     */
    public static function normalizarStatus(array $status, string $metaPhoneId): array
    {
        $timestamp = isset($status['timestamp']) ? (int) $status['timestamp'] : time();

        return [
            'event' => 'status',
            'from' => (string) ($status['recipient_id'] ?? ''),
            'wa_id' => (string) ($status['id'] ?? ''),
            'status' => (string) ($status['status'] ?? ''),
            'meta_phone_id' => $metaPhoneId,
            'timestamp' => date('c', $timestamp),
            'raw' => $status,
        ];
    }

    /**
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>
     */
    public static function aPayloadCrm(array $event): array
    {
        if (($event['event'] ?? '') === 'status') {
            return [
                'event' => 'status',
                'wa_id' => $event['wa_id'] ?? '',
                'sender_phone' => $event['from'] ?? '',
                'status' => $event['status'] ?? '',
                'direction' => 'inbound',
                'timestamp' => $event['timestamp'] ?? now()->toIso8601String(),
                'roma_contract_version' => ContratoMensajeWhatsapp::VERSION,
            ];
        }

        return [
            'wa_id' => $event['wa_id'] ?? '',
            'sender_name' => $event['sender_name'] ?? null,
            'sender_phone' => $event['from'] ?? '',
            'sender_name' => $event['profile_name'] ?? null,
            'from' => $event['from'] ?? '',
            'message_body' => $event['text'] ?? ($event['interactive']['title'] ?? ''),
            'content' => $event['text'] ?? ($event['interactive']['title'] ?? ''),
            'direction' => 'incoming',
            'message_type' => $event['message_type'] ?? 'text',
            'interactive' => $event['interactive'] ?? null,
            'image_url' => $event['image_url'] ?? null,
            'media_url' => $event['media_url'] ?? null,
            'local_url' => $event['local_url'] ?? null,
            'mime_type' => $event['mime_type'] ?? null,
            'timestamp' => $event['timestamp'] ?? now()->toIso8601String(),
            'raw' => $event['raw'] ?? null,
            'roma_contract_version' => ContratoMensajeWhatsapp::VERSION,
        ];
    }
}
