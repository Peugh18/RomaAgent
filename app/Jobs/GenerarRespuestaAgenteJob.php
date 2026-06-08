<?php

namespace App\Jobs;

use App\Actions\GenerarRespuestaAgente;
use App\Exceptions\GeminiQuotaExceededException;
use App\Models\Message;
use App\Services\AlertaCuotaGemini;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class GenerarRespuestaAgenteJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    public int $timeout = 240;

    public int $uniqueFor = 30;

    public function __construct(public Message $mensajeEntrante) {}

    public function uniqueId(): string
    {
        return 'ia_agent_'.$this->mensajeEntrante->phone_number.'_'.$this->mensajeEntrante->id;
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [45, 90, 120];
    }

    public function handle(GenerarRespuestaAgente $generador): void
    {
        if (! $generador->debeResponder($this->mensajeEntrante)) {
            return;
        }

        Log::info('GenerarRespuestaAgenteJob: processing', [
            'message_id' => $this->mensajeEntrante->id,
            'phone' => $this->mensajeEntrante->phone_number,
        ]);

        try {
            $generador->ejecutar($this->mensajeEntrante);
        } catch (GeminiQuotaExceededException $e) {
            app(AlertaCuotaGemini::class)->marcar($e->getMessage(), $e->retryAfterSeconds);

            Log::warning('GenerarRespuestaAgenteJob: cuota Gemini, reintentando', [
                'message_id' => $this->mensajeEntrante->id,
                'phone' => $this->mensajeEntrante->phone_number,
                'retry_in' => $e->retryAfterSeconds,
                'attempt' => $this->attempts(),
            ]);

            if ($this->attempts() < $this->tries) {
                $this->release($e->retryAfterSeconds);

                return;
            }

            throw $e;
        }
    }

    public function failed(?\Throwable $exception): void
    {
        if ($exception instanceof GeminiQuotaExceededException) {
            app(AlertaCuotaGemini::class)->marcar($exception->getMessage(), $exception->retryAfterSeconds);
        }

        Log::error('GenerarRespuestaAgenteJob: failed', [
            'message_id' => $this->mensajeEntrante->id,
            'phone' => $this->mensajeEntrante->phone_number,
            'error' => $exception?->getMessage(),
        ]);
    }
}
