<?php

namespace Database\Seeders;

use App\Models\ZonaEnvio;
use Illuminate\Database\Seeder;

class ZonaEnvioSeeder extends Seeder
{
    public function run(): void
    {
        $limaDelivery = [
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
            'Barranco' => 15,
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

        foreach ($limaDelivery as $distrito => $costo) {
            ZonaEnvio::updateOrCreate(
                [
                    'departamento' => 'Lima',
                    'distrito' => $distrito,
                    'tipo_envio' => 'delivery_local',
                ],
                [
                    'provincia' => 'Lima',
                    'costo_referencial' => $costo,
                    'activo' => true,
                    'datos_requeridos' => ['direccion_exacta', 'referencia', 'celular_contacto'],
                ]
            );
        }

        ZonaEnvio::updateOrCreate(
            [
                'departamento' => 'Lima',
                'tipo_envio' => 'agencia_shalom',
            ],
            [
                'provincia' => 'Lima',
                'distrito' => 'Todo Lima',
                'costo_referencial' => 10.00,
                'activo' => true,
                'datos_requeridos' => ['agencia_destino', 'dni_receptor', 'celular_contacto'],
            ]
        );

        ZonaEnvio::updateOrCreate(
            [
                'departamento' => 'Provincia',
                'tipo_envio' => 'agencia_shalom',
            ],
            [
                'provincia' => 'Provincia',
                'distrito' => 'Cualquier Provincia',
                'costo_referencial' => 12.00,
                'activo' => true,
                'datos_requeridos' => ['departamento', 'provincia', 'agencia_destino', 'dni_receptor', 'celular_contacto'],
            ]
        );
    }
}
