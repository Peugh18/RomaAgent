<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Trabajador = 'trabajador';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Trabajador => 'Trabajador',
        };
    }
}
