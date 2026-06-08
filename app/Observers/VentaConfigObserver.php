<?php

namespace App\Observers;

use App\Models\VentaConfig;
use App\Support\InvalidatesPromptCache;
use Illuminate\Support\Facades\Log;

class VentaConfigObserver
{
    use InvalidatesPromptCache;

    public function updated(VentaConfig $ventaConfig): void
    {
        $this->invalidar($ventaConfig);
    }

    public function created(VentaConfig $ventaConfig): void
    {
        $this->invalidar($ventaConfig);
    }

    private function invalidar(VentaConfig $ventaConfig): void
    {
        $this->invalidarCachePrompt($ventaConfig->company_setting_id);

        Log::info('Cache de ventas invalidada', [
            'company_setting_id' => $ventaConfig->company_setting_id,
        ]);
    }
}
