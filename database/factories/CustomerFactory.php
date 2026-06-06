<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'phone_number' => '+51'.fake()->numerify('9########'),
            'name' => fake()->firstName(),
            'ia_paused' => false,
        ];
    }

    public function iaPausada(?string $reason = 'Pago por revisar'): static
    {
        return $this->state(fn (): array => [
            'ia_paused' => true,
            'ia_pause_reason' => $reason,
        ]);
    }
}
