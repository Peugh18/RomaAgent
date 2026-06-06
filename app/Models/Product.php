<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;
    public const ESTADO_DISPONIBLE = 'disponible';

    public const ESTADO_AGOTADO = 'agotado';

    public const ESTADO_OCULTO = 'oculto';

    protected $fillable = [
        'name',
        'description',
        'price',
        'price_tiktok',
        'discount',
        'discount_active',
        'category_id',
        'status',
        'tags_ia',
    ];

    protected $casts = [
        'tags_ia' => 'array',
        'price' => 'decimal:2',
        'price_tiktok' => 'decimal:2',
        'discount' => 'decimal:2',
        'discount_active' => 'boolean',
    ];

    public function descuentoPromoActivo(): bool
    {
        return $this->discount_active
            && $this->discount !== null
            && (float) $this->discount > 0;
    }

    public function precioNormalConPromo(): ?float
    {
        if (! $this->descuentoPromoActivo()) {
            return null;
        }

        return max(0.01, (float) $this->price - (float) $this->discount);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function similares(): HasMany
    {
        return $this->hasMany(ProductoSimilar::class)->orderBy('orden');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function stockTotal(): int
    {
        $this->loadMissing('variants');

        $total = 0;

        foreach ($this->variants as $variant) {
            foreach ($variant->sizes_stock ?? [] as $qty) {
                $total += max(0, (int) $qty);
            }
        }

        return $total;
    }

    public function sincronizarEstadoPorStock(): void
    {
        if ($this->status === self::ESTADO_OCULTO) {
            return;
        }

        $nuevoEstado = $this->stockTotal() > 0
            ? self::ESTADO_DISPONIBLE
            : self::ESTADO_AGOTADO;

        if ($this->status !== $nuevoEstado) {
            $this->status = $nuevoEstado;
            $this->saveQuietly();
        }
    }

    public function marcarComoOculto(): void
    {
        if ($this->status !== self::ESTADO_OCULTO) {
            $this->status = self::ESTADO_OCULTO;
            $this->saveQuietly();
        }
    }

    public function desmarcarOculto(): void
    {
        if ($this->status === self::ESTADO_OCULTO) {
            $this->sincronizarEstadoPorStock();
        }
    }
}
