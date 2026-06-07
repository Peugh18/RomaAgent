<?php

namespace App\Observers;

use App\Models\AgenteConfig;
use Illuminate\Support\Facades\Cache;

/**
 * Observer que invalida caché del prompt cuando cambia configuración del agente
 */
class AgenteConfigObserver
{
    /**
     * Handle the AgenteConfig "updated" event.
     */
    public function updated(AgenteConfig $agenteConfig): void
    {
        $this->invalidarCacheSeccion($agenteConfig);
    }

    /**
     * Handle the AgenteConfig "created" event.
     */
    public function created(AgenteConfig $agenteConfig): void
    {
        $this->invalidarCacheSeccion($agenteConfig);
    }

    /**
     * Invalida solo la sección de agente y el prompt completo
     *
     * NOTA: Usa Cache::forget() simple para compatibilidad sin Redis
     */
    private function invalidarCacheSeccion(AgenteConfig $agenteConfig): void
    {
        $configId = $agenteConfig->company_setting_id;

        // Invalidar solo la sección de agente (sin tags, compatible con cualquier driver)
        Cache::forget("seccion_agente_{$configId}");

        // Invalidar el prompt completo (se regenerará con nueva info)
        Cache::forget("prompt_unificado_v2_{$configId}");

        \Log::info('Cache de agente invalidada', [
            'company_setting_id' => $configId,
            'changed_fields' => $agenteConfig->getChanges(),
        ]);
    }
}
