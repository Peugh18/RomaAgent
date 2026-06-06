<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanySetting extends Model
{
    /** @use HasFactory<\Database\Factories\CompanySettingFactory> */
    use HasFactory;

    protected $fillable = [
        'company_name',
        'ruc',
        'razon_social',
        'celular',
        'email',
        'website',
        'logo_path',
        'actividad_economica',
        'tono_bot',
        'estilo_comunicacion',
        'personalidad_bot',
        'respuesta_si_es_bot',
        'moneda',
        'metodos_pago',
        'horario_atencion',
        'politica_devoluciones',
        'restricciones_especiales',
        'informacion_adicional',
        'social_networks',
        'address',
        'standard_size',
        'agente_ia_activado',
        'agente_ia_modelo',
        'agente_ia_api_key_encrypted',
        'agente_ia_temperatura',
        'saludo_inicial',
        'reglas_comunicacion',
        'flujo_ventas',
        'plantillas_datos',
        'horario_entregas',
        'horario_shalom',
        'protocolo_traspaso',
        'mensaje_recordatorio_3min',
        'mensaje_recordatorio_15min',
        'mensaje_recordatorio_datos',
        'comision_tarjeta',
        'formato_registro_venta',
        'mensaje_comprobante_recibido',
        'mensaje_comprobante_fuera_horario',
        'mensaje_pedido_confirmado',
        'mensaje_espera_link_tarjeta',
    ];

    protected function casts(): array
    {
        return [
            'social_networks' => 'array',
            'metodos_pago' => 'array',
            'plantillas_datos' => 'array',
            'agente_ia_activado' => 'boolean',
            'agente_ia_temperatura' => 'decimal:2',
            'comision_tarjeta' => 'decimal:2',
        ];
    }

    protected $hidden = [
        'agente_ia_api_key_encrypted',
    ];
}
