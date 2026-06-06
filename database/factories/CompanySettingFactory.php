<?php

namespace Database\Factories;

use App\Models\CompanySetting;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Crypt;

/**
 * @extends Factory<CompanySetting>
 */
class CompanySettingFactory extends Factory
{
    protected $model = CompanySetting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_name' => fake()->company(),
            'celular' => '+51'.fake()->numerify('9########'),
            'email' => fake()->companyEmail(),
            'agente_ia_activado' => false,
            'agente_ia_modelo' => 'gemini-2.5-flash',
            'agente_ia_temperatura' => 0.7,
        ];
    }

    public function withIaEnabled(?string $apiKey = 'test-gemini-key'): static
    {
        return $this->state(fn (): array => [
            'agente_ia_activado' => true,
            'agente_ia_api_key_encrypted' => Crypt::encryptString($apiKey ?? 'test-gemini-key'),
        ]);
    }
}
