<?php

namespace App\Models;

use Database\Factories\VentaConfigFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Configuración de ventas y métodos de pago
 * Antes: campos en CompanySetting
 */
class VentaConfig extends Model
{
    /** @use HasFactory<VentaConfigFactory> */
    use HasFactory;

    protected $fillable = [
        'company_setting_id',
        'moneda',
        'metodos_pago',
        'comision_tarjeta',
        'link_pago_tarjeta',
        'formato_registro_venta',
        'protocolo_traspaso',
    ];

    protected function casts(): array
    {
        return [
            'metodos_pago' => 'array',
            'comision_tarjeta' => 'decimal:2',
        ];
    }

    public function companySetting(): BelongsTo
    {
        return $this->belongsTo(CompanySetting::class);
    }

    /**
     * Verifica si un método de pago está activo
     */
    public function aceptaMetodoPago(string $metodo): bool
    {
        $metodos = $this->metodos_pago ?? [];

        return in_array($metodo, $metodos, true);
    }

    /**
     * Calcula total con comisión de tarjeta
     */
    public function calcularTotalConComision(float $subtotal): float
    {
        if ($this->comision_tarjeta <= 0) {
            return $subtotal;
        }

        return $subtotal * (1 + ($this->comision_tarjeta / 100));
    }

    /**
     * Símbolo de la moneda
     */
    public function simboloMoneda(): string
    {
        return match ($this->moneda) {
            'PEN' => 'S/',
            'USD' => '$',
            'EUR' => '€',
            default => $this->moneda,
        };
    }
}
