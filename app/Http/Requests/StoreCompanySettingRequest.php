<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCompanySettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_name' => 'nullable|string|max:255',
            'ruc' => 'nullable|string|max:20',
            'razon_social' => 'nullable|string|max:255',
            'celular' => 'nullable|string|max:20',
            'email' => 'nullable|email',
            'website' => 'nullable|url',
            'logo_path' => 'nullable|string',
            'actividad_economica' => 'nullable|string|max:255',
            'tono_bot' => 'nullable|string|max:255',
            'estilo_comunicacion' => 'nullable|string|max:255',
            'personalidad_bot' => 'nullable|string',
            'respuesta_si_es_bot' => 'nullable|string',
            'moneda' => 'nullable|string|max:3',
            'metodos_pago' => 'nullable|array',
            'horario_atencion' => 'nullable|string',
            'politica_devoluciones' => 'nullable|string',
            'restricciones_especiales' => 'nullable|string',
            'informacion_adicional' => 'nullable|string',
            'social_networks' => 'nullable|array',
            'address' => 'nullable|string',
            'standard_size' => 'nullable|string|max:32',
            'agente_ia_activado' => 'nullable|boolean',
            'agente_ia_modelo' => 'nullable|string|max:255',
            'agente_ia_api_key' => 'nullable|string|max:1024',
            'agente_ia_temperatura' => 'nullable|numeric|min:0|max:1',
            'saludo_inicial' => 'nullable|string',
            'reglas_comunicacion' => 'nullable|string',
            'flujo_ventas' => 'nullable|string',
            'plantillas_datos' => 'nullable|array',
            'horario_entregas' => 'nullable|string|max:255',
            'horario_shalom' => 'nullable|string|max:255',
            'protocolo_traspaso' => 'nullable|string',
            'mensaje_recordatorio_3min' => 'nullable|string',
            'mensaje_recordatorio_15min' => 'nullable|string',
            'mensaje_recordatorio_datos' => 'nullable|string',
            'formato_registro_venta' => 'nullable|string',
            'mensaje_comprobante_recibido' => 'nullable|string',
            'mensaje_comprobante_fuera_horario' => 'nullable|string',
            'mensaje_pedido_confirmado' => 'nullable|string',
            'mensaje_espera_link_tarjeta' => 'nullable|string',
            'comision_tarjeta' => 'nullable|numeric|min:0|max:100',
        ];
    }
}
