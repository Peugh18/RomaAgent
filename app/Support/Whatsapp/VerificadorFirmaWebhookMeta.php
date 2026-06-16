<?php

namespace App\Support\Whatsapp;

use App\Infrastructure\Whatsapp\MetaWhatsAppSettings;
use Illuminate\Http\Request;

class VerificadorFirmaWebhookMeta
{
    public static function esFirmaValida(Request $request): bool
    {
        $firmaMeta = $request->header('x-hub-signature-256');

        if (! $firmaMeta) {
            return false;
        }

        $secreto = MetaWhatsAppSettings::appSecret();

        if (empty($secreto)) {
            // Si no hay secreto configurado, por defecto no bloqueamos si estamos en local/testing,
            // pero en producción debería bloquear.
            return ! app()->environment('production');
        }

        $payload = $request->getContent();

        $firmaCalculada = 'sha256='.hash_hmac('sha256', $payload, $secreto);

        return hash_equals($firmaCalculada, $firmaMeta);
    }
}
