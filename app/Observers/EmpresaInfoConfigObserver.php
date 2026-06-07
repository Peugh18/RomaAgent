<?php

namespace App\Observers;

use App\Models\EmpresaInfoConfig;
use Illuminate\Support\Facades\Cache;

/**
 * Observer que invalida caché del prompt cuando cambian datos de empresa
 */
class EmpresaInfoConfigObserver
{
    public function updated(EmpresaInfoConfig $empresaInfo): void
    {
        $this->invalidarCache($empresaInfo);
    }

    public function created(EmpresaInfoConfig $empresaInfo): void
    {
        $this->invalidarCache($empresaInfo);
    }

    /**
     * Invalida solo la sección de empresa y el prompt completo
     *
     * NOTA: Usa Cache::forget() simple para compatibilidad sin Redis
     */
    private function invalidarCache(EmpresaInfoConfig $empresaInfo): void
    {
        $configId = $empresaInfo->company_setting_id;

        // Invalidar solo la sección de empresa (sin tags, compatible con cualquier driver)
        Cache::forget("seccion_empresa_{$configId}");

        // Invalidar prompt completo
        Cache::forget("prompt_unificado_v2_{$configId}");

        \Log::info('Cache de empresa invalidada', [
            'company_setting_id' => $configId,
        ]);
    }
}
