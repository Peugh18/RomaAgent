<?php

namespace App\Models;

use Database\Factories\CompanySettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Company Setting - Coordinador de configuraciones
 *
 * ANTES: 58 campos en una tabla (God Object)
 * AHORA: Relación 1:1 con tablas especializadas
 *
 * Tablas relacionadas:
 * - empresa_info_configs: Datos básicos de empresa
 * - agente_configs: Configuración IA
 * - mensaje_configs: Plantillas de mensajes
 * - venta_configs: Configuración de ventas
 * - horario_configs: Horarios y políticas
 */
class CompanySetting extends Model
{
    /** @use HasFactory<CompanySettingFactory> */
    use HasFactory;

    protected $fillable = [
        // Campos legacy - serán migrados gradualmente
        'company_name', // Temporal - migrar a empresaInfo
    ];

    /**
     * Relación: Información de la empresa
     */
    public function empresaInfo(): HasOne
    {
        return $this->hasOne(EmpresaInfoConfig::class);
    }

    /**
     * Relación: Configuración del Agente IA
     */
    public function agente(): HasOne
    {
        return $this->hasOne(AgenteConfig::class);
    }

    /**
     * Relación: Mensajes y plantillas
     */
    public function mensajes(): HasOne
    {
        return $this->hasOne(MensajeConfig::class);
    }

    /**
     * Relación: Configuración de ventas
     */
    public function ventas(): HasOne
    {
        return $this->hasOne(VentaConfig::class);
    }

    /**
     * Relación: Horarios y políticas
     */
    public function horarios(): HasOne
    {
        return $this->hasOne(HorarioConfig::class);
    }

    /**
     * Obtener o crear configuración relacionada
     */
    public function obtenerOCrearAgente(): AgenteConfig
    {
        return $this->agente()->firstOrCreate([
            'company_setting_id' => $this->id,
        ]);
    }

    public function obtenerOCrearMensajes(): MensajeConfig
    {
        return $this->mensajes()->firstOrCreate([
            'company_setting_id' => $this->id,
        ]);
    }

    public function obtenerOCrearVentas(): VentaConfig
    {
        return $this->ventas()->firstOrCreate([
            'company_setting_id' => $this->id,
        ]);
    }

    public function obtenerOCrearEmpresaInfo(): EmpresaInfoConfig
    {
        return $this->empresaInfo()->firstOrCreate([
            'company_setting_id' => $this->id,
        ]);
    }

    public function obtenerOCrearHorarios(): HorarioConfig
    {
        return $this->horarios()->firstOrCreate([
            'company_setting_id' => $this->id,
        ]);
    }
}
