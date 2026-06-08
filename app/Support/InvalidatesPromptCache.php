<?php

namespace App\Support;

use App\Models\CompanySetting;
use Illuminate\Support\Facades\Cache;

trait InvalidatesPromptCache
{
    protected function invalidarCachePrompt(?int $settingsId = null): void
    {
        $settingsId ??= CompanySetting::query()->value('id');

        if ($settingsId !== null) {
            Cache::forget('contexto_prompt_completo_'.$settingsId);
        }
    }
}
