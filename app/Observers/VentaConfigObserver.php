<?php

namespace App\Observers;

use App\Models\VentaConfig;
use Illuminate\Support\Facades\Cache;

/**
 * Observer que invalida caché del prompt cuando cambian configuraciones de ventas
 *
 * NOTA: Usa Cache::forget() simple para compatibilidad sin Redis
 */
class VentaConfigObserver
{
    public function updated(VentaConfig $ventaConfig): void
    {
        $this->invalidarCache($ventaConfig);
    }

    public function created(VentaConfig $ventaConfig): void
    {
        $this->invalidarCache($ventaConfig);
    }

    /**
     * Invalida solo la sección de ventas y el prompt completo
     */
    private function invalidarCache(VentaConfig $ventaConfig): void
    {
        $configId = $ventaConfig->company_setting_id;

        // Invalidar solo la sección de ventas (sin tags, compatible con cualquier driver)
        Cache::forget("seccion_ventas_{$configId}");

        // Invalidar prompt completo
        Cache::forget("prompt_unificado_v2_{$configId}");

        \Log::info('Cache de ventas invalidada', [
            'company_setting_id' => $configId,
        ]);
    }
}
