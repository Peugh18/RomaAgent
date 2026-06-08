<?php

namespace App\Observers;

use App\Models\AgenteConfig;
use App\Support\InvalidatesPromptCache;
use Illuminate\Support\Facades\Log;

class AgenteConfigObserver
{
    use InvalidatesPromptCache;

    public function updated(AgenteConfig $agenteConfig): void
    {
        $this->invalidar($agenteConfig);
    }

    public function created(AgenteConfig $agenteConfig): void
    {
        $this->invalidar($agenteConfig);
    }

    private function invalidar(AgenteConfig $agenteConfig): void
    {
        $this->invalidarCachePrompt($agenteConfig->company_setting_id);

        Log::info('Cache de agente invalidada', [
            'company_setting_id' => $agenteConfig->company_setting_id,
            'changed_fields' => $agenteConfig->getChanges(),
        ]);
    }
}
