<?php

namespace App\Observers;

use App\Models\MensajeConfig;
use App\Support\InvalidatesPromptCache;
use Illuminate\Support\Facades\Log;

class MensajeConfigObserver
{
    use InvalidatesPromptCache;

    public function updated(MensajeConfig $mensajeConfig): void
    {
        $this->invalidar($mensajeConfig);
    }

    public function created(MensajeConfig $mensajeConfig): void
    {
        $this->invalidar($mensajeConfig);
    }

    private function invalidar(MensajeConfig $mensajeConfig): void
    {
        $this->invalidarCachePrompt($mensajeConfig->company_setting_id);

        Log::info('Cache de mensajes invalidada', [
            'company_setting_id' => $mensajeConfig->company_setting_id,
        ]);
    }
}
