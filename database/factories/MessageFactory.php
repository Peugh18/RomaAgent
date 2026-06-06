<?php

namespace Database\Factories;

use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    protected $model = Message::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'message_id' => 'wamid.'.fake()->uuid(),
            'phone_number' => '51'.fake()->numerify('#########'),
            'customer_name' => fake()->name(),
            'content' => fake()->sentence(),
            'direction' => 'incoming',
            'status' => 'delivered',
            'whatsapp_timestamp' => now(),
            'metadata' => ['type' => 'text'],
        ];
    }

    public function incoming(): static
    {
        return $this->state(fn (): array => [
            'direction' => 'incoming',
            'status' => 'delivered',
        ]);
    }

    public function outgoing(): static
    {
        return $this->state(fn (): array => [
            'direction' => 'outgoing',
            'status' => 'pending',
        ]);
    }
}
