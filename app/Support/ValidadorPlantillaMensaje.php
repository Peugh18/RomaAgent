<?php

namespace App\Support;

class ValidadorPlantillaMensaje
{
    /** @var list<string> */
    public const VARIABLES = ['{nombre}', '{producto}', '{color}', '{total}', '{distrito}', '{metodo_pago}'];

    /**
     * Detecta placeholders incorrectos tipo {"Mariela"} en vez de {producto}.
     */
    public static function tieneFormatoIncorrecto(?string $plantilla): bool
    {
        if ($plantilla === null || trim($plantilla) === '') {
            return false;
        }

        return (bool) preg_match('/\{"[^"]+"\}/', $plantilla);
    }

    /**
     * Corrige doble llave {{nombre}} → {nombre} y elimina JSON literales inválidos.
     */
    public static function normalizar(?string $plantilla): string
    {
        if ($plantilla === null) {
            return '';
        }

        $texto = trim($plantilla);
        if ($texto === '') {
            return '';
        }

        $texto = (string) preg_replace('/\{\{(\w+)\}\}/', '{$1}', $texto);

        if (self::tieneFormatoIncorrecto($texto)) {
            return '';
        }

        return $texto;
    }

    /**
     * @return list<string>
     */
    public static function variablesSinResolver(string $plantilla): array
    {
        preg_match_all('/\{(\w+)\}/', $plantilla, $matches);
        $encontradas = array_unique($matches[1] ?? []);
        $validas = array_map(fn (string $v): string => trim($v, '{}'), self::VARIABLES);
        $validas = array_map(fn (string $v): string => str_replace(['{', '}'], '', $v), self::VARIABLES);

        $sinResolver = [];
        foreach ($encontradas as $variable) {
            if (! in_array($variable, $validas, true)) {
                $sinResolver[] = '{'.$variable.'}';
            }
        }

        return $sinResolver;
    }
}
