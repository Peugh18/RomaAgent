<?php

namespace App\Models;

use App\Enums\SaleStatus;
use Database\Factories\SaleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sale extends Model
{
    /** @use HasFactory<SaleFactory> */
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'phone_number',
        'product_id',
        'product_variant_id',
        'product_name',
        'color',
        'size',
        'quantity',
        'unit_price',
        'delivery_cost',
        'total_amount',
        'payment_method',
        'delivery_type',
        'delivery_district',
        'status',
        'customer_data',
        'notes',
        'agent_metadata',
        'payment_received_at',
        'confirmed_at',
        'shipped_at',
        'delivered_at',
        'confirmed_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => SaleStatus::class,
            'customer_data' => 'array',
            'agent_metadata' => 'array',
            'unit_price' => 'decimal:2',
            'delivery_cost' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'quantity' => 'integer',
            'payment_received_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function confirmedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by_user_id');
    }

    public function marcarPagoRecibido(): void
    {
        $this->update([
            'status' => SaleStatus::PagoRecibido,
            'payment_received_at' => now(),
        ]);
    }

    public function esPagoTarjeta(): bool
    {
        return str_contains(mb_strtolower((string) $this->payment_method), 'tarjeta');
    }

    public function puedeVerificarPago(): bool
    {
        if ($this->esPagoTarjeta()) {
            return $this->status === SaleStatus::PagoPendiente;
        }

        return $this->status === SaleStatus::PagoRecibido;
    }

    public function puedeMarcarEnviado(): bool
    {
        return $this->status === SaleStatus::Confirmado;
    }

    public function puedeMarcarEntregado(): bool
    {
        return $this->status === SaleStatus::Enviado;
    }

    public function puedeCancelar(): bool
    {
        return ! in_array($this->status, [SaleStatus::Cancelado, SaleStatus::Entregado], true);
    }

    public function cancelar(): void
    {
        $this->update([
            'status' => SaleStatus::Cancelado,
        ]);
    }

    public function estaAbierto(): bool
    {
        return $this->status->esPipelineAbierto()
            && ! in_array($this->status, [SaleStatus::Confirmado, SaleStatus::Enviado, SaleStatus::Entregado], true);
    }

    /**
     * @return array{nombre: string|null, direccion: string|null, maps_url: string|null}
     */
    public function datosEntregaResumen(): array
    {
        $data = $this->customer_data ?? [];

        return [
            'nombre' => $this->customer?->name
                ?? ($data['nombre'] ?? $data['name'] ?? null),
            'direccion' => $data['direccion'] ?? $data['address'] ?? $data['ubicacion_actual'] ?? null,
            'maps_url' => $data['maps_url'] ?? null,
        ];
    }
}
