<?php

namespace App\Models;

use Database\Factories\MensajeConfigFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mensajes automáticos y plantillas
 * Antes: campos en CompanySetting
 */
class MensajeConfig extends Model
{
    /** @use HasFactory<MensajeConfigFactory> */
    use HasFactory;

    protected $fillable = [
        'company_setting_id',
        'saludo_inicial',
        'reglas_comunicacion',
        'flujo_ventas',
        'recordatorio_3min',
        'recordatorio_15min',
        'recordatorio_datos',
        'pedido_confirmado',
        'pedido_enviado',
        'pedido_entregado',
        'recordatorio_motorizado',
        'recordatorio_shalom',
        'comprobante_recibido',
        'comprobante_fuera_horario',
        'espera_link_tarjeta',
    ];

    public function companySetting(): BelongsTo
    {
        return $this->belongsTo(CompanySetting::class);
    }

    /**
     * Reemplaza variables en un mensaje template
     *
     * @param  array<string, string>  $variables
     */
    public function renderTemplate(string $field, array $variables = []): string
    {
        $template = $this->$field ?? '';

        foreach ($variables as $key => $value) {
            $template = str_replace("{{{$key}}}", $value, $template);
        }

        return $template;
    }
}
