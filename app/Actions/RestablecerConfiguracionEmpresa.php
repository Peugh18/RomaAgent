<?php

namespace App\Actions;

use App\Models\AgenteConfig;
use App\Models\CompanySetting;
use App\Models\EmpresaInfoConfig;
use App\Models\HorarioConfig;
use App\Models\LogIA;
use App\Models\MensajeConfig;
use App\Models\VentaConfig;
use App\Support\MapeadorConfiguracionLegacy;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RestablecerConfiguracionEmpresa
{
    public function handle(): CompanySetting
    {
        return DB::transaction(function (): CompanySetting {
            // Delete request logs
            LogIA::query()->delete();

            // Fetch current settings if they exist
            $settings = CompanySetting::query()->first();

            // Forget cached prompt if settings exist
            if ($settings !== null) {
                Cache::forget('contexto_prompt_completo_'.$settings->id);
            }

            // Delete all related configuration tables to fully reset state
            EmpresaInfoConfig::query()->delete();
            MensajeConfig::query()->delete();
            AgenteConfig::query()->delete();
            VentaConfig::query()->delete();
            HorarioConfig::query()->delete();

            // Default configuration values
            $defaults = MapeadorConfiguracionLegacy::valoresPorDefecto();

            if ($settings !== null) {
                // Reset company name and apply default configuration
                $settings->update(['company_name' => null]);
                MapeadorConfiguracionLegacy::aplicarDesdeArray($settings, $defaults);
                // Refresh the model instance to get latest relations
                $settings = $settings->fresh();
            } else {
                // Create a new settings record with null company name
                $settings = CompanySetting::query()->create(['company_name' => null]);
                MapeadorConfiguracionLegacy::aplicarDesdeArray($settings, $defaults);
            }

            // Forget cache again for the (new or refreshed) settings
            Cache::forget('contexto_prompt_completo_'.$settings->id);

            return $settings;
        });
    }
}
