<?php

namespace App\Models;

use Database\Factories\HorarioConfigFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Horarios y políticas de atención
 * Antes: campos en CompanySetting
 */
class HorarioConfig extends Model
{
    /** @use HasFactory<HorarioConfigFactory> */
    use HasFactory;

    protected $fillable = [
        'company_setting_id',
        'horario_atencion',
        'horario_entregas',
        'horario_shalom',
        'politica_devoluciones',
        'restricciones_especiales',
        'plantillas_datos',
        'standard_size',
    ];

    protected function casts(): array
    {
        return [
            'plantillas_datos' => 'array',
        ];
    }

    public function companySetting(): BelongsTo
    {
        return $this->belongsTo(CompanySetting::class);
    }

    /**
     * Verifica si estamos en horario de atención
     */
    public function estaEnHorarioAtencion(): bool
    {
        // Implementar lógica según formato guardado
        // Ejemplo: "Lunes a Viernes 9:00 - 18:00"
        return true; // Placeholder
    }
}
