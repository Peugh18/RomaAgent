<?php

namespace App\Http\Controllers\Api;

use App\Actions\UpdateMessageStatus;
use App\Http\Controllers\Controller;
use App\Infrastructure\Whatsapp\MetaWhatsAppSettings;
use App\Jobs\ProcessWebhookPayloadJob;
use App\Support\Whatsapp\NormalizadorWebhookMeta;
use App\Support\Whatsapp\VerificadorFirmaWebhookMeta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WhatsappWebhookController extends Controller
{
    public function __construct(
        private UpdateMessageStatus $updateStatus,
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
        if (! VerificadorFirmaWebhookMeta::esFirmaValida($request)) {
            Log::warning('Firma de webhook de Meta inválida.');

            return response()->json(['error' => 'Invalid signature'], 403);
        }

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
                    $payload = NormalizadorWebhookMeta::aPayloadCrm($event);

                    try {
                        ProcessWebhookPayloadJob::dispatch($payload);
                        $processed++;
                    } catch (\Throwable $e) {
                        Log::error('WhatsappWebhook: failed to dispatch job', [
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
}
