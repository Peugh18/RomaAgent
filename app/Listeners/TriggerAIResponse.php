<?php

namespace App\Listeners;

use App\Events\InboundMessageReceived;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class TriggerAIResponse
{
    /**
     * Create the event listener.
     */
    public function __construct(
        private \App\Actions\GenerarRespuestaAgente $generarRespuestaAgente
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
                \Illuminate\Support\Facades\Log::info('Sticker entrante: no se encola respuesta IA', [
                    'phone' => $message->phone_number,
                ]);
            } elseif (in_array($tipo, ['image', 'audio'], true)) {
                \App\Jobs\ProcessMediaThenRespondJob::dispatch($message->id);
            } else {
                try {
                    if ($this->generarRespuestaAgente->debeResponder($message)) {
                        app(\App\Services\EncolarRespuestaAgente::class)->despachar($message);
                    }
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Error encolando respuesta IA', [
                        'error' => $e->getMessage(),
                        'phone' => $message->phone_number,
                    ]);
                }
            }
        }
    }
}
