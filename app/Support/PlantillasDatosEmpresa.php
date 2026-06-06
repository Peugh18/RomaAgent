<?php

namespace App\Support;

class PlantillasDatosEmpresa
{
    /**
     * @return array{motorizado: array<string, string>, shalom: array<string, string>}
     */
    public static function porDefecto(): array
    {
        return [
            'motorizado' => [
                'campo_0' => '✅ NOMBRE COMPLETO',
                'campo_1' => '✅ CELULAR',
                'campo_2' => '✅ DIRECCIÓN',
                'campo_3' => '✅ UBICACIÓN ACTUAL',
            ],
            'shalom' => [
                'campo_0' => '✅ Nombre completo',
                'campo_1' => '✅ Número de DNI',
                'campo_2' => '✅ Número de celular',
                'campo_3' => '✅ Sede exacta de shalom',
            ],
        ];
    }

    /**
     * @param  mixed  $plantillas
     * @return array{motorizado: array<string, string>, shalom: array<string, string>}
     */
    public static function normalizar(mixed $plantillas): array
    {
        $defecto = self::porDefecto();

        if (! is_array($plantillas) || $plantillas === []) {
            return $defecto;
        }

        $motorizado = is_array($plantillas['motorizado'] ?? null) ? $plantillas['motorizado'] : [];
        $shalom = is_array($plantillas['shalom'] ?? null) ? $plantillas['shalom'] : [];

        if ($motorizado === [] && $shalom === []) {
            return $defecto;
        }

        return [
            'motorizado' => $motorizado !== [] ? $motorizado : $defecto['motorizado'],
            'shalom' => $shalom !== [] ? $shalom : $defecto['shalom'],
        ];
    }

    public static function estaVacia(mixed $plantillas): bool
    {
        if (! is_array($plantillas) || $plantillas === []) {
            return true;
        }

        $motorizado = $plantillas['motorizado'] ?? [];
        $shalom = $plantillas['shalom'] ?? [];

        return (! is_array($motorizado) || $motorizado === [])
            && (! is_array($shalom) || $shalom === []);
    }
}
