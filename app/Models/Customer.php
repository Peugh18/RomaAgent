<?php

namespace App\Models;

use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    protected $fillable = [
        'phone_number',
        'name',
        'ia_paused',
        'ia_pause_reason',
        'notes',
        'active_sale_id',
        'last_inbound_at',
        'reminder_3min_sent_at',
        'reminder_15min_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'ia_paused' => 'boolean',
            'last_inbound_at' => 'datetime',
            'reminder_3min_sent_at' => 'datetime',
            'reminder_15min_sent_at' => 'datetime',
        ];
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function activeSale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'active_sale_id');
    }

    public function labels()
    {
        return $this->belongsToMany(Label::class);
    }

    public static function resolverDesdeMensaje(string $phoneNumber, ?string $name = null): self
    {
        $customer = self::query()->firstOrCreate(
            ['phone_number' => $phoneNumber]
        );

        if ($name !== null && $name !== '') {
            $isCurrentEmptyOrPhone = $customer->name === null || $customer->name === '' || $customer->name === $phoneNumber;
            if ($isCurrentEmptyOrPhone && $customer->name !== $name) {
                $customer->update(['name' => $name]);
            }
        }

        return $customer->fresh();
    }

    public function pausarIa(?string $reason = null): void
    {
        $this->update([
            'ia_paused' => true,
            'ia_pause_reason' => $reason,
        ]);
    }

    public function reanudarIa(): void
    {
        $this->update([
            'ia_paused' => false,
            'ia_pause_reason' => null,
        ]);
    }

    public function asignarPedidoActivo(Sale $sale): void
    {
        $this->update(['active_sale_id' => $sale->id]);
    }
}
