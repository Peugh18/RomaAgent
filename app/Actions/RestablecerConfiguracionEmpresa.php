<?php

namespace App\Actions;

use App\Models\CompanySetting;
use App\Support\PlantillasDatosEmpresa;
use App\Models\DeliveryZone;
use App\Models\LogIA;
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

            $defaults = $this->valoresPorDefecto();

            if ($settings !== null) {
                $settings->update($defaults);
                $settings = $settings->fresh();
            } else {
                $settings = CompanySetting::query()->create($defaults);
            }

            Cache::forget('contexto_prompt_completo_'.$settings->id);

            return $settings;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function valoresPorDefecto(): array
    {
        return [
            'company_name' => null,
            'ruc' => null,
            'razon_social' => null,
            'celular' => null,
            'email' => null,
            'website' => null,
            'logo_path' => null,
            'actividad_economica' => null,
            'tono_bot' => 'cálido y cercano',
            'estilo_comunicacion' => 'natural',
            'personalidad_bot' => null,
            'respuesta_si_es_bot' => null,
            'moneda' => 'PEN',
            'metodos_pago' => [],
            'horario_atencion' => null,
            'politica_devoluciones' => null,
            'restricciones_especiales' => null,
            'informacion_adicional' => null,
            'social_networks' => [
                'instagram' => '',
                'facebook' => '',
                'tiktok' => '',
            ],
            'address' => null,
            'standard_size' => 'UNICA',
            'agente_ia_activado' => false,
            'agente_ia_modelo' => 'gemini-2.5-flash',
            'agente_ia_api_key_encrypted' => null,
            'agente_ia_temperatura' => 0.7,
            'saludo_inicial' => null,
            'reglas_comunicacion' => null,
            'flujo_ventas' => null,
            'plantillas_datos' => PlantillasDatosEmpresa::porDefecto(),
            'horario_entregas' => null,
            'horario_shalom' => null,
            'protocolo_traspaso' => null,
            'mensaje_recordatorio_3min' => null,
            'mensaje_recordatorio_15min' => null,
            'mensaje_recordatorio_datos' => null,
            'comision_tarjeta' => 5.00,
            'formato_registro_venta' => null,
            'mensaje_comprobante_recibido' => null,
            'mensaje_comprobante_fuera_horario' => null,
            'mensaje_pedido_confirmado' => null,
            'mensaje_espera_link_tarjeta' => null,
        ];
    }
}
