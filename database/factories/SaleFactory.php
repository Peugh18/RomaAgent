<?php

namespace Database\Factories;

use App\Enums\SaleStatus;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    protected $model = Sale::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $unitPrice = fake()->randomFloat(2, 80, 250);
        $delivery = fake()->randomElement([0, 12, 15, 18]);

        return [
            'customer_id' => Customer::factory(),
            'phone_number' => '+51'.fake()->numerify('9########'),
            'product_name' => fake()->word(),
            'color' => fake()->colorName(),
            'size' => 'UNICA',
            'quantity' => 1,
            'unit_price' => $unitPrice,
            'delivery_cost' => $delivery,
            'total_amount' => $unitPrice + $delivery,
            'payment_method' => 'yape',
            'delivery_type' => 'motorizado',
            'status' => SaleStatus::PagoRecibido,
            'payment_received_at' => now(),
        ];
    }

    public function forProduct(Product $product, ?ProductVariant $variant = null): static
    {
        $variant ??= $product->variants()->first();

        return $this->state(fn (): array => [
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
            'product_name' => $product->name,
            'color' => $variant?->color,
            'unit_price' => $product->price,
            'total_amount' => (float) $product->price,
        ]);
    }

    public function pagoRecibido(): static
    {
        return $this->state(fn (): array => [
            'status' => SaleStatus::PagoRecibido,
            'payment_received_at' => now(),
        ]);
    }

    public function confirmado(): static
    {
        return $this->state(fn (): array => [
            'status' => SaleStatus::Confirmado,
            'confirmed_at' => now(),
        ]);
    }

    public function entregado(): static
    {
        return $this->state(fn (): array => [
            'status' => SaleStatus::Entregado,
            'delivered_at' => now(),
        ]);
    }
}
