<?php

namespace App\Support;

/**
 * @deprecated Esta clase se mantiene por compatibilidad. Las plantillas de datos
 * de envío han sido eliminadas. Se reimplementará junto al nuevo sistema de envíos.
 */
class PlantillasDatosEmpresa
{
    /**
     * @return array<string, mixed>
     */
    public static function porDefecto(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public static function normalizar(mixed $plantillas): array
    {
        return [];
    }

    public static function estaVacia(mixed $plantillas): bool
    {
        return true;
    }
}
