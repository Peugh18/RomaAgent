<?php

namespace App\Http\Controllers\Api;

use App\Actions\ProcessIncomingMessage;
use App\Actions\UpdateMessageStatus;
use App\Http\Controllers\Controller;
use App\Infrastructure\Whatsapp\MetaWhatsAppSettings;
use App\Services\Media\DescargadorMediaWhatsapp;
use App\Support\Whatsapp\NormalizadorWebhookMeta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WhatsappWebhookController extends Controller
{
    public function __construct(
        private ProcessIncomingMessage $processIncoming,
        private UpdateMessageStatus $updateStatus,
        private DescargadorMediaWhatsapp $descargadorMedia,
    ) {}

    public function handle(Request $request): Response|JsonResponse
    {
        if ($request->isMethod('GET')) {
            return $this->verify($request);
        }

        return $this->receive($request);
    }

    public function verify(Request $request): Response|JsonResponse
    {
        $mode = (string) $request->query('hub_mode', $request->query('hub.mode', ''));
        $token = (string) $request->query('hub_verify_token', $request->query('hub.verify_token', ''));
        $challenge = (string) $request->query('hub_challenge', $request->query('hub.challenge', ''));

        if ($mode === 'subscribe' && $token !== '' && hash_equals(MetaWhatsAppSettings::verifyToken(), $token)) {
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        return response()->json(['error' => 'Invalid verification token'], 403);
    }

    public function receive(Request $request): JsonResponse
    {
        $body = $request->all();

        if (($body['object'] ?? '') !== 'whatsapp_business_account') {
            return response()->json(['error' => 'Not a WhatsApp event'], 404);
        }

        $processed = 0;

        foreach ($body['entry'] ?? [] as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            foreach ($entry['changes'] ?? [] as $change) {
                if (! is_array($change)) {
                    continue;
                }

                $value = is_array($change['value'] ?? null) ? $change['value'] : [];
                $metaPhoneId = (string) ($value['metadata']['phone_number_id'] ?? MetaWhatsAppSettings::phoneNumberId());

                $contacts = is_array($value['contacts'] ?? null) ? $value['contacts'] : [];
                $contactProfiles = [];
                foreach ($contacts as $contact) {
                    if (is_array($contact) && isset($contact['wa_id'])) {
                        $contactProfiles[(string) $contact['wa_id']] = $contact['profile']['name'] ?? null;
                    }
                }

                foreach ($value['messages'] ?? [] as $message) {
                    if (! is_array($message)) {
                        continue;
                    }

                    $contacts = is_array($value['contacts'] ?? null) ? $value['contacts'] : [];
                    $event = NormalizadorWebhookMeta::normalizarMensaje($message, $metaPhoneId, $contacts);
                    $event = $this->enriquecerMedia($event);
                    $payload = NormalizadorWebhookMeta::aPayloadCrm($event);

                    try {
                        $this->processIncoming->execute($payload);
                        $processed++;
                    } catch (\Throwable $e) {
                        Log::error('WhatsappWebhook: inbound failed', [
                            'wa_id' => $payload['wa_id'] ?? null,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                foreach ($value['statuses'] ?? [] as $status) {
                    if (! is_array($status)) {
                        continue;
                    }

                    $event = NormalizadorWebhookMeta::normalizarStatus($status, $metaPhoneId);
                    $payload = NormalizadorWebhookMeta::aPayloadCrm($event);

                    try {
                        $this->updateStatus->execute($payload);
                        $processed++;
                    } catch (\Throwable $e) {
                        Log::error('WhatsappWebhook: status failed', [
                            'wa_id' => $payload['wa_id'] ?? null,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
        }

        return response()->json([
            'status' => 'success',
            'events_processed' => $processed,
        ]);
    }

    /**
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>
     */
    private function enriquecerMedia(array $event): array
    {
        $messageType = (string) ($event['message_type'] ?? 'text');
        if (! $this->descargadorMedia->esDescargable($messageType)) {
            return $event;
        }

        $raw = is_array($event['raw'] ?? null) ? $event['raw'] : [];
        $waId = (string) ($event['wa_id'] ?? '');

        $resolved = $this->descargadorMedia->descargar($waId, $messageType, $raw);
        if ($resolved === null) {
            return $event;
        }

        $event['media_url'] = $resolved['url'];
        $event['local_url'] = $resolved['local_url'];
        $event['mime_type'] = $resolved['mime'];

        if (in_array($messageType, ['image', 'sticker'], true)) {
            $event['image_url'] = $resolved['local_url'] ?? $resolved['url'];
        }

        return $event;
    }
}
