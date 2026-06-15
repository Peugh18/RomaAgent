<?php

namespace App\Services;

use App\Models\LogIA;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class AlertaCuotaGemini
{
    private const CACHE_KEY = 'gemini_cuota_excedida';

    public function marcar(string $mensaje, int $reintentarEnSegundos = 60): void
    {
        $reintentarEnSegundos = max(15, $reintentarEnSegundos);
        $disponibleEn = now()->addSeconds($reintentarEnSegundos);

        Cache::put(self::CACHE_KEY, [
            'activa' => true,
            'mensaje' => $mensaje,
            'detectado_en' => now()->toIso8601String(),
            'disponible_aprox_en' => $disponibleEn->toIso8601String(),
            'reintentar_en_segundos' => $reintentarEnSegundos,
        ], $disponibleEn->addMinutes(10));
    }

    public function limpiar(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function obtener(): ?array
    {
        $cache = Cache::get(self::CACHE_KEY);

        if (is_array($cache) && ($cache['activa'] ?? false)) {
            return $this->enriquecer($cache);
        }

        $ultimoError = LogIA::obtenerUltimoError();

        if ($ultimoError !== null
            && (int) $ultimoError->http_status === 429
            && $ultimoError->created_at !== null
            && $ultimoError->created_at->greaterThan(now()->subHour())
        ) {
            return $this->enriquecer([
                'activa' => true,
                'mensaje' => (string) $ultimoError->error_mensaje,
                'detectado_en' => $ultimoError->created_at->toIso8601String(),
                'disponible_aprox_en' => $ultimoError->created_at->copy()->addMinutes(1)->toIso8601String(),
                'reintentar_en_segundos' => 60,
                'desde_log' => true,
            ]);
        }

        return null;
    }

    public function estaActiva(): bool
    {
        return $this->obtener() !== null;
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function enriquecer(array $datos): array
    {
        $disponibleEn = isset($datos['disponible_aprox_en'])
            ? Carbon::parse((string) $datos['disponible_aprox_en'])
            : null;

        $segundosRestantes = $disponibleEn !== null && $disponibleEn->isFuture()
            ? now()->diffInSeconds($disponibleEn)
            : 0;

        return array_merge($datos, [
            'activa' => true,
            'titulo' => 'Cuota de Gemini agotada',
            'descripcion' => 'El bot no puede responder hasta que se renueve la cuota o cambies de API key/modelo.',
            'segundos_restantes' => $segundosRestantes,
            'puede_reintentar' => $segundosRestantes <= 0,
        ]);
    }
}
