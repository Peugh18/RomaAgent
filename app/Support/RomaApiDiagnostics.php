<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;

class RomaApiDiagnostics
{
    /**
     * @return array<string, mixed>
     */
    public static function run(?string $probePhone = null): array
    {
        $baseUrl = rtrim((string) config('services.roma.url'), '/');
        $token = (string) config('services.roma.token');
        $probePhone = $probePhone ?: '51999999999';

        $result = [
            'roma_api_url' => $baseUrl,
            'sync_token_configured' => $token !== '',
            'health' => null,
            'send_probe' => null,
            'issues' => [],
        ];

        if ($baseUrl === '') {
            $result['issues'][] = 'ROMA_API_URL vacío en el .env de RomaAgent.';

            return $result;
        }

        try {
            $healthResponse = Http::withHeaders([
                'Accept' => 'application/json',
                'ngrok-skip-browser-warning' => 'true',
            ])->timeout(10)->get($baseUrl.'/api/health');

            $result['health'] = [
                'status' => $healthResponse->status(),
                'body' => $healthResponse->json(),
            ];
        } catch (\Throwable $e) {
            $result['issues'][] = 'No se pudo contactar roma-api: '.$e->getMessage();
        }

        try {
            $probeResponse = Http::withHeaders(RomaApiHeaders::forJsonPost())
                ->timeout(15)
                ->post($baseUrl.'/api/messages', [
                    'to' => $probePhone,
                    'type' => 'text',
                    'text' => ['body' => 'romaagent_diagnostic_probe'],
                    'wa_id' => 'out_diagnostic_probe',
                    'sender_phone' => $probePhone,
                    'message_body' => 'romaagent_diagnostic_probe',
                    'direction' => 'outbound',
                    'context' => [
                        'source' => 'laravel_agent',
                        'message_id' => 'out_diagnostic_probe',
                    ],
                    'roma_contract_version' => 1,
                ]);

            $body = $probeResponse->json();
            $result['send_probe'] = [
                'status' => $probeResponse->status(),
                'body' => $body,
            ];

            if (is_array($body) && ($body['meta_phone_id'] ?? null) === '') {
                $result['issues'][] = 'roma-api no tiene meta_phone_id configurado. Revisa WHATSAPP_PHONE_NUMBER_ID en el .env de roma-api y reinicia el servidor.';
            }

            if (is_array($body) && ($body['ok'] ?? null) === false) {
                $error = $body['error'] ?? $body['meta_error'] ?? null;
                if (is_array($error)) {
                    $result['issues'][] = 'Meta rechazó el envío de prueba: '.json_encode($error);
                }
            }
        } catch (\Throwable $e) {
            $result['issues'][] = 'Prueba de envío falló: '.$e->getMessage();
        }

        if ($result['issues'] === []) {
            $result['issues'][] = 'Conexión con roma-api OK. Si el chat falla, revisa permisos del token de Meta.';
        }

        return $result;
    }
}
