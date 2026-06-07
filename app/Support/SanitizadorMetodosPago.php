<?php

namespace App\Support;

class SanitizadorMetodosPago
{
    private const MAX_IMAGEN_URL_LENGTH = 2048;

    /**
     * Elimina data-URIs embebidos que inflan el JSON en base de datos.
     *
     * @param  array<int, array<string, mixed>>|null  $metodos
     * @return array<int, array<string, mixed>>|null
     */
    public static function sanitizar(?array $metodos): ?array
    {
        if ($metodos === null) {
            return null;
        }

        return array_values(array_map(
            fn (array $metodo): array => self::sanitizarMetodo($metodo),
            $metodos
        ));
    }

    /**
     * @param  array<string, mixed>  $metodo
     * @return array<string, mixed>
     */
    private static function sanitizarMetodo(array $metodo): array
    {
        if (! isset($metodo['imagen_url']) || ! is_string($metodo['imagen_url'])) {
            return $metodo;
        }

        $url = $metodo['imagen_url'];

        if (str_starts_with($url, 'data:') || strlen($url) > self::MAX_IMAGEN_URL_LENGTH) {
            unset($metodo['imagen_url']);
        }

        return $metodo;
    }
}
