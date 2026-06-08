<?php

namespace App\Support;

use App\Infrastructure\Whatsapp\MetaWhatsAppClient;
use App\Infrastructure\Whatsapp\MetaWhatsAppSettings;

class WhatsappDiagnostics
{
    /**
     * @return array<string, mixed>
     */
    public static function run(?string $probePhone = null): array
    {
        $probePhone = $probePhone ?: '51999999999';

        $result = [
            'whatsapp_configured' => MetaWhatsAppSettings::isConfigured(),
            'phone_number_id' => MetaWhatsAppSettings::phoneNumberId(),
            'public_url' => rtrim((string) config('app.public_url', config('app.url')), '/'),
            'webhook_url' => rtrim((string) config('app.public_url', config('app.url')), '/').'/api/whatsapp/webhook',
            'send_probe' => null,
            'issues' => [],
        ];

        if (! MetaWhatsAppSettings::isConfigured()) {
            $result['issues'][] = 'WHATSAPP_ACCESS_TOKEN o WHATSAPP_PHONE_NUMBER_ID vacíos en el .env.';
        }

        if (MetaWhatsAppSettings::verifyToken() === '') {
            $result['issues'][] = 'WHATSAPP_VERIFY_TOKEN vacío — Meta no podrá verificar el webhook.';
        }

        if ($result['public_url'] === '' || str_contains($result['public_url'], 'localhost')) {
            $result['issues'][] = 'PUBLIC_APP_URL debe ser tu URL pública (ngrok puerto 8000).';
        }

        if (! MetaWhatsAppSettings::isConfigured()) {
            return $result;
        }

        try {
            $client = app(MetaWhatsAppClient::class);
            $probe = $client->send([
                'to' => $probePhone,
                'type' => 'text',
                'text' => ['body' => 'romaagent_diagnostic_probe'],
                'message_body' => 'romaagent_diagnostic_probe',
            ]);

            $result['send_probe'] = $probe;

            if (($probe['ok'] ?? null) === false) {
                $error = $probe['error'] ?? $probe['meta_error'] ?? null;
                $result['issues'][] = 'Meta rechazó el envío de prueba: '.json_encode($error);
            }
        } catch (\Throwable $e) {
            $result['issues'][] = 'Prueba de envío falló: '.$e->getMessage();
        }

        if ($result['issues'] === []) {
            $result['issues'][] = 'WhatsApp OK. Webhook en Meta: '.$result['webhook_url'];
        }

        return $result;
    }
}
