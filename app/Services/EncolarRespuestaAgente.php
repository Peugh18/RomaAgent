<?php

namespace App\Services;

use App\Jobs\EsperarRespuestaAgenteJob;
use App\Models\Message;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EncolarRespuestaAgente
{
    public function despachar(Message $mensaje): void
    {
        $phone = $mensaje->phone_number;
        $token = Str::uuid()->toString();
        $segundos = $this->segundosDebounce();

        Cache::put($this->cacheKey($phone), [
            'token' => $token,
            'message_id' => $mensaje->id,
        ], now()->addMinutes(10));

        EsperarRespuestaAgenteJob::dispatch($phone, $token)
            ->delay(now()->addSeconds($segundos));

        Log::info('Encolando respuesta IA con debounce', [
            'phone' => $phone,
            'message_id' => $mensaje->id,
            'debounce_seconds' => $segundos,
        ]);
    }

    /**
     * @return array{token: string, message_id: int}|null
     */
    public function estadoDebounce(string $phoneNumber): ?array
    {
        $state = Cache::get($this->cacheKey($phoneNumber));

        return is_array($state) ? $state : null;
    }

    public function limpiarDebounce(string $phoneNumber): void
    {
        Cache::forget($this->cacheKey($phoneNumber));
    }

    public function segundosDebounce(): int
    {
        return max(3, (int) config('services.agente.debounce_seconds', 8));
    }

    private function cacheKey(string $phoneNumber): string
    {
        return 'ia_debounce:'.$phoneNumber;
    }
}
