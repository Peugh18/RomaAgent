<?php

namespace App\Observers;

use App\Models\HorarioConfig;
use App\Support\InvalidatesPromptCache;
use Illuminate\Support\Facades\Log;

class HorarioConfigObserver
{
    use InvalidatesPromptCache;

    public function updated(HorarioConfig $horarioConfig): void
    {
        $this->invalidar($horarioConfig);
    }

    public function created(HorarioConfig $horarioConfig): void
    {
        $this->invalidar($horarioConfig);
    }

    private function invalidar(HorarioConfig $horarioConfig): void
    {
        $this->invalidarCachePrompt($horarioConfig->company_setting_id);

        Log::info('Cache de horarios invalidada', [
            'company_setting_id' => $horarioConfig->company_setting_id,
        ]);
    }
}
