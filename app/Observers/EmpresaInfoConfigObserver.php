<?php

namespace App\Observers;

use App\Models\EmpresaInfoConfig;
use App\Support\InvalidatesPromptCache;
use Illuminate\Support\Facades\Log;

class EmpresaInfoConfigObserver
{
    use InvalidatesPromptCache;

    public function updated(EmpresaInfoConfig $empresaInfo): void
    {
        $this->invalidar($empresaInfo);
    }

    public function created(EmpresaInfoConfig $empresaInfo): void
    {
        $this->invalidar($empresaInfo);
    }

    private function invalidar(EmpresaInfoConfig $empresaInfo): void
    {
        $this->invalidarCachePrompt($empresaInfo->company_setting_id);

        Log::info('Cache de empresa invalidada', [
            'company_setting_id' => $empresaInfo->company_setting_id,
        ]);
    }
}
