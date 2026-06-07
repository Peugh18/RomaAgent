<?php

namespace App\Observers;

use App\Models\MensajeConfig;
use Illuminate\Support\Facades\Cache;

/**
 * Observer que invalida caché del prompt cuando cambian mensajes/plantillas
 */
class MensajeConfigObserver
{
    public function updated(MensajeConfig $mensajeConfig): void
    {
        $this->invalidarCache($mensajeConfig);
    }

    public function created(MensajeConfig $mensajeConfig): void
    {
        $this->invalidarCache($mensajeConfig);
    }

    /**
     * Invalida solo la sección de mensajes y el prompt completo
     *
     * NOTA: Usa Cache::forget() simple para compatibilidad sin Redis
     */
    private function invalidarCache(MensajeConfig $mensajeConfig): void
    {
        $configId = $mensajeConfig->company_setting_id;

        // Invalidar solo la sección de mensajes (sin tags, compatible con cualquier driver)
        Cache::forget("seccion_mensajes_{$configId}");

        // Invalidar prompt completo
        Cache::forget("prompt_unificado_v2_{$configId}");

        \Log::info('Cache de mensajes invalidada', [
            'company_setting_id' => $configId,
        ]);
    }
}
