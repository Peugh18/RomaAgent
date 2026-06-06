<?php

namespace App\Models;

use App\Enums\SaleStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sale extends Model
{
    /** @use HasFactory<\Database\Factories\SaleFactory> */
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

    public function estaAbierto(): bool
    {
        return $this->status->esPipelineAbierto()
            && ! in_array($this->status, [SaleStatus::Confirmado, SaleStatus::Enviado], true);
    }
}
