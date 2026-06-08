<?php

namespace App\Jobs;

use App\Actions\GenerarRespuestaAgente;
use App\Models\Customer;
use App\Models\Message;
use App\Services\EncolarRespuestaAgente;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class EsperarRespuestaAgenteJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(
        public string $phoneNumber,
        public string $token,
    ) {}

    public function handle(
        EncolarRespuestaAgente $encolar,
        GenerarRespuestaAgente $generador,
    ): void {
        $state = $encolar->estadoDebounce($this->phoneNumber);

        if (! is_array($state) || ($state['token'] ?? '') !== $this->token) {
            Log::info('EsperarRespuestaAgenteJob: debounce reemplazado por mensaje más reciente', [
                'phone' => $this->phoneNumber,
            ]);

            return;
        }

        $segundosDebounce = $encolar->segundosDebounce();
        $ultimoEntrante = $this->ultimoMensajeEntranteAt();

        if ($ultimoEntrante !== null && $ultimoEntrante->diffInSeconds(now()) < $segundosDebounce) {
            $restante = $segundosDebounce - $ultimoEntrante->diffInSeconds(now());

            Log::info('EsperarRespuestaAgenteJob: esperando más mensajes del cliente', [
                'phone' => $this->phoneNumber,
                'retry_in' => $restante,
            ]);

            $this->release(max(1, $restante));

            return;
        }

        $messageId = (int) ($state['message_id'] ?? 0);
        $mensaje = Message::query()->find($messageId);

        if ($mensaje === null) {
            Log::warning('EsperarRespuestaAgenteJob: mensaje disparador no encontrado', [
                'phone' => $this->phoneNumber,
                'message_id' => $messageId,
            ]);

            $encolar->limpiarDebounce($this->phoneNumber);

            return;
        }

        if (! $generador->debeResponder($mensaje)) {
            $encolar->limpiarDebounce($this->phoneNumber);

            return;
        }

        $encolar->limpiarDebounce($this->phoneNumber);

        Log::info('EsperarRespuestaAgenteJob: generando respuesta tras debounce', [
            'phone' => $this->phoneNumber,
            'message_id' => $mensaje->id,
        ]);

        GenerarRespuestaAgenteJob::dispatch($mensaje);
    }

    private function ultimoMensajeEntranteAt(): ?Carbon
    {
        $customer = Customer::query()
            ->where('phone_number', $this->phoneNumber)
            ->first();

        if ($customer?->last_inbound_at !== null) {
            return $customer->last_inbound_at;
        }

        $ultimo = Message::query()
            ->where('phone_number', $this->phoneNumber)
            ->where('direction', 'incoming')
            ->orderByDesc('created_at')
            ->value('created_at');

        return $ultimo instanceof Carbon ? $ultimo : null;
    }
}
