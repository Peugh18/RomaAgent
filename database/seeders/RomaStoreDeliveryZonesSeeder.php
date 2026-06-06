<?php

namespace Database\Seeders;

use App\Models\CompanySetting;
use App\Models\DeliveryZone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

class RomaStoreDeliveryZonesSeeder extends Seeder
{
    /**
     * Tarifario motorizado Roma Store (Lima) + Shalom fijo S/ 10 en Lima.
     *
     * @return list<array{district: string, cost_motorizado: float, cost_shalom: float}>
     */
    private function zonasRomaStore(): array
    {
        $shalomLima = 10.00;

        $motorizado = [
            'Cercado de Lima' => 12,
            'Breña' => 14,
            'Jesús María' => 14,
            'La Victoria' => 11,
            'Lince' => 14,
            'Magdalena del Mar' => 15,
            'Miraflores' => 15,
            'Pueblo Libre' => 15,
            'Rímac' => 11,
            'San Borja' => 11,
            'San Isidro' => 14,
            'San Miguel' => 15,
            'Santiago de Surco' => 12,
            'Surquillo' => 13,
            'Carabayllo' => 20,
            'Comas' => 16,
            'Independencia' => 15,
            'Los Olivos' => 15,
            'Puente Piedra' => 20,
            'San Martín de Porres' => 15,
            'Santa Rosa' => 35,
            'Ancón' => 35,
            'Ate' => 10,
            'El Agustino' => 10,
            'Lurigancho-Chosica' => 16,
            'San Juan de Lurigancho' => 14,
            'Santa Anita' => 10,
            'Chaclacayo' => 16,
            'Cieneguilla' => 30,
            'Barranco' => 11,
            'Chorrillos' => 15,
            'Lurín' => 30,
            'Pachacámac' => 30,
            'Pucusana' => 35,
            'Punta Hermosa' => 35,
            'Punta Negra' => 35,
            'San Bartolo' => 35,
            'San Juan de Miraflores' => 16,
            'Santa María del Mar' => 35,
            'Villa El Salvador' => 16,
            'Villa María del Triunfo' => 16,
        ];

        $zonas = [];
        foreach ($motorizado as $district => $costo) {
            $zonas[] = [
                'district' => $district,
                'cost_motorizado' => (float) $costo,
                'cost_shalom' => $shalomLima,
            ];
        }

        $zonas[] = [
            'district' => 'Provincia (Shalom)',
            'cost_motorizado' => 0,
            'cost_shalom' => 12.00,
        ];

        return $zonas;
    }

    public function run(): void
    {
        DeliveryZone::query()->delete();

        foreach ($this->zonasRomaStore() as $zona) {
            DeliveryZone::query()->create($zona);
        }

        $settings = CompanySetting::query()->first();
        if ($settings !== null) {
            Cache::forget('contexto_prompt_completo_'.$settings->id);
        }

        $this->command?->info('Importadas '.count($this->zonasRomaStore()).' zonas de delivery Roma Store.');
    }
}
