<?php

namespace App\Support;

use Throwable;

class WhatsappSendError
{
    /**
     * @var list<string>
     */
    private const PERMANENT_MARKERS = [
        '131005',
        '131008',
        '131030',
        '131031',
        '190',
        'OAuthException',
        'Access denied',
        'Invalid OAuth',
        'Permission denied',
        'WhatsApp directo no configurado',
        'WHATSAPP_ACCESS_TOKEN',
        'WHATSAPP_PHONE_NUMBER_ID',
    ];

    public static function isPermanent(Throwable $exception): bool
    {
        $message = $exception->getMessage();

        foreach (self::PERMANENT_MARKERS as $marker) {
            if (str_contains($message, $marker)) {
                return true;
            }
        }

        return false;
    }

    public static function userMessage(Throwable $exception): string
    {
        $message = $exception->getMessage();

        if (str_contains($message, '131005') || str_contains($message, '131008') || str_contains($message, 'Access denied')) {
            return 'WhatsApp rechazó el envío: token de Meta inválido o sin permisos. Revisa WHATSAPP_ACCESS_TOKEN y WHATSAPP_PHONE_NUMBER_ID en el .env.';
        }

        if (str_contains($message, 'meta_phone_id')) {
            return 'WHATSAPP_PHONE_NUMBER_ID no configurado o inválido. Agrégalo en el .env.';
        }

        if (str_contains($message, '131030')) {
            return 'WhatsApp en modo desarrollo: el número destino no está en la lista de prueba de Meta.';
        }

        if (str_contains($message, '131031')) {
            return 'WhatsApp rechazó el envío: la cuenta está bloqueada o restringida por Meta.';
        }

        if (str_contains($message, 'WhatsApp directo no configurado') || str_contains($message, 'WHATSAPP_ACCESS_TOKEN')) {
            return 'WhatsApp no configurado. Agrega WHATSAPP_ACCESS_TOKEN, WHATSAPP_PHONE_NUMBER_ID y WHATSAPP_VERIFY_TOKEN al .env.';
        }

        return mb_strlen($message) > 280 ? mb_substr($message, 0, 280).'…' : $message;
    }
}
