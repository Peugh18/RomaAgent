<?php

namespace App\Support;

use App\Models\CompanySetting;
use Illuminate\Support\Facades\Cache;

trait InvalidatesPromptCache
{
    private function invalidarCachePrompt(): void
    {
        $settingsId = CompanySetting::query()->value('id');

        if ($settingsId !== null) {
            Cache::forget('contexto_prompt_completo_'.$settingsId);
        }
    }
}
