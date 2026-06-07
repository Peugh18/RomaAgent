<?php

namespace App\Observers;

use App\Models\HorarioConfig;
use Illuminate\Support\Facades\Cache;

/**
 * Observer que invalida caché del prompt cuando cambian horarios/políticas
 *
 * NOTA: Usa Cache::forget() simple para compatibilidad sin Redis
 */
class HorarioConfigObserver
{
    public function updated(HorarioConfig $horarioConfig): void
    {
        $this->invalidarCache($horarioConfig);
    }

    public function created(HorarioConfig $horarioConfig): void
    {
        $this->invalidarCache($horarioConfig);
    }

    /**
     * Invalida solo la sección de horarios y el prompt completo
     */
    private function invalidarCache(HorarioConfig $horarioConfig): void
    {
        $configId = $horarioConfig->company_setting_id;

        // Invalidar solo la sección de horarios (sin tags, compatible con cualquier driver)
        Cache::forget("seccion_horarios_{$configId}");

        // Invalidar prompt completo
        Cache::forget("prompt_unificado_v2_{$configId}");

        \Log::info('Cache de horarios invalidada', [
            'company_setting_id' => $configId,
        ]);
    }
}
