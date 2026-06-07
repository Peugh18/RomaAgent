<?php

namespace App\Actions;

use App\Models\CompanySetting;
use App\Models\DeliveryZone;
use App\Models\LogIA;
use App\Support\MapeadorConfiguracionLegacy;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RestablecerConfiguracionEmpresa
{
    public function handle(): CompanySetting
    {
        return DB::transaction(function (): CompanySetting {
            DeliveryZone::query()->delete();
            LogIA::query()->delete();

            $settings = CompanySetting::query()->first();

            if ($settings !== null) {
                Cache::forget('contexto_prompt_completo_'.$settings->id);
            }

            $defaults = MapeadorConfiguracionLegacy::valoresPorDefecto();

            if ($settings !== null) {
                $settings->update(['company_name' => null]);
                MapeadorConfiguracionLegacy::aplicarDesdeArray($settings, $defaults);
                $settings = $settings->fresh();
            } else {
                $settings = CompanySetting::query()->create(['company_name' => null]);
                MapeadorConfiguracionLegacy::aplicarDesdeArray($settings, $defaults);
            }

            Cache::forget('contexto_prompt_completo_'.$settings->id);

            return $settings;
        });
    }
}
