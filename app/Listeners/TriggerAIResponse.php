<?php

namespace App\Listeners;

use App\Actions\GenerarRespuestaAgente;
use App\Events\InboundMessageReceived;
use App\Jobs\ProcessMediaThenRespondJob;
use App\Services\EncolarRespuestaAgente;
use Illuminate\Support\Facades\Log;

class TriggerAIResponse
{
    /**
     * Create the event listener.
     */
    public function __construct(
        private GenerarRespuestaAgente $generarRespuestaAgente
    ) {}

    /**
     * Handle the event.
     */
    public function handle(InboundMessageReceived $event): void
    {
        $message = $event->message;

        if ($event->esMensajeNuevo && $message->direction === 'incoming') {
            $tipo = is_array($message->metadata) ? ($message->metadata['type'] ?? 'text') : 'text';
            if ($tipo === 'sticker') {
                Log::info('Sticker entrante: no se encola respuesta IA', [
                    'phone' => $message->phone_number,
                ]);
            } elseif (in_array($tipo, ['image', 'audio'], true)) {
                ProcessMediaThenRespondJob::dispatch($message->id);
            } else {
                try {
                    if ($this->generarRespuestaAgente->debeResponder($message)) {
                        app(EncolarRespuestaAgente::class)->despachar($message);
                    }
                } catch (\Exception $e) {
                    Log::error('Error encolando respuesta IA', [
                        'error' => $e->getMessage(),
                        'phone' => $message->phone_number,
                    ]);
                }
            }
        }
    }
}
