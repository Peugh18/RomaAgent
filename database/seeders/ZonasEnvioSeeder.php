<?php

namespace Database\Seeders;

use App\Models\ZonaEnvio;
use Illuminate\Database\Seeder;

class ZonasEnvioSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $zonas = [
            ['Cercado de Lima', 12],
            ['Breña', 14],
            ['Jesús María', 14],
            ['La Victoria', 11],
            ['Lince', 14],
            ['Magdalena del Mar', 15],
            ['Miraflores', 15],
            ['Pueblo Libre', 15],
            ['Rímac', 11],
            ['San Borja', 11],
            ['San Isidro', 14],
            ['San Miguel', 15],
            ['Santiago de Surco', 12],
            ['Surquillo', 13],
            ['Carabayllo', 20],
            ['Comas', 16],
            ['Independencia', 15],
            ['Los Olivos', 15],
            ['Puente Piedra', 20],
            ['San Martín de Porres', 15],
            ['Santa Rosa', 35],
            ['Ancón', 35],
            ['Ate', 10],
            ['El Agustino', 10],
            ['Lurigancho-Chosica', 16],
            ['San Juan de Lurigancho', 14],
            ['Santa Anita', 10],
            ['Chaclacayo', 16],
            ['Cieneguilla', 30],
            ['Barranco', 15],
            ['Chorrillos', 15],
            ['Lurín', 30],
            ['Pachacámac', 30],
            ['Pucusana', 35],
            ['Punta Hermosa', 35],
            ['Punta Negra', 35],
            ['San Bartolo', 35],
            ['San Juan de Miraflores', 16],
            ['Santa María del Mar', 35],
            ['Villa El Salvador', 16],
            ['Villa María del Triunfo', 16],
        ];

        foreach ($zonas as $zona) {
            ZonaEnvio::firstOrCreate([
                'departamento' => 'Lima',
                'provincia' => 'Lima',
                'distrito' => $zona[0],
            ], [
                'tipo_envio' => 'motorizado',
                'costo_referencial' => $zona[1],
                'activo' => true,
            ]);
        }
    }
}
