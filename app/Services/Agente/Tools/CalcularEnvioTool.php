<?php

namespace App\Services\Agente\Tools;

use App\Models\DeliveryZone;
use App\Services\ConfiguracionEmpresa;

class CalcularEnvioTool
{
    /** @return array{name:string,description:string,parameters:array<string,mixed>} */
    public static function definition(): array
    {
        return [
            'name' => 'calcular_envio',
            'description' => 'Calcula costo estimado de envío por método (motorizado o shalom) y distrito usando DeliveryZone.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'method' => ['type' => 'string', 'enum' => ['motorizado', 'shalom']],
                    'district' => ['type' => 'string'],
                ],
                'required' => ['method', 'district'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public static function execute(array $args): array
    {
        $method = strtolower(trim((string) ($args['method'] ?? '')));
        $district = trim((string) ($args['district'] ?? ''));

        if (! in_array($method, ['motorizado', 'shalom'], true) || $district === '') {
            return ['ok' => false, 'error' => 'Parámetros inválidos'];
        }

        $zone = DeliveryZone::query()
            ->whereRaw('LOWER(district) = ?', [mb_strtolower($district)])
            ->first();

        if ($zone === null) {
            // Fallback de Provincia: si no encuentra el distrito, buscamos la tarifa genérica de provincia
            $provinciaZone = DeliveryZone::query()
                ->where('district', 'LIKE', '%Provincia%')
                ->first();

            if ($provinciaZone !== null) {
                // Asumimos Shalom para envíos a provincia por defecto, a menos que especifiquen lo contrario
                $cost = $method === 'motorizado' && $provinciaZone->cost_motorizado > 0
                    ? (float) $provinciaZone->cost_motorizado
                    : (float) $provinciaZone->cost_shalom;

                $methodLabel = $method === 'motorizado' && $provinciaZone->cost_motorizado > 0
                    ? 'Motorizado (Provincia)'
                    : 'Shalom (Provincia)';

                $moneda = (new ConfiguracionEmpresa)->obtenerMoneda();

                return [
                    'ok' => true,
                    'found' => true,
                    'method' => $method,
                    'district' => $provinciaZone->district,
                    'currency' => $moneda,
                    'cost' => $cost,
                    'method_label' => $methodLabel,
                    'nota' => 'El distrito solicitado no está en la base de datos local. Se aplicó la tarifa general de provincia automáticamente.',
                ];
            }

            return ['ok' => true, 'found' => false];
        }

        $cost = $method === 'motorizado' ? (float) $zone->cost_motorizado : (float) $zone->cost_shalom;
        $moneda = (new ConfiguracionEmpresa)->obtenerMoneda();

        return [
            'ok' => true,
            'found' => true,
            'method' => $method,
            'district' => $zone->district,
            'currency' => $moneda,
            'cost' => $cost,
            'method_label' => $method === 'motorizado' ? 'Motorizado' : 'Shalom',
        ];
    }
}
