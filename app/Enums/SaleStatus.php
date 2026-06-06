<?php

namespace App\Enums;

enum SaleStatus: string
{
    case Consultando = 'consultando';
    case Cotizando = 'cotizando';
    case DatosListos = 'datos_listos';
    case PagoPendiente = 'pago_pendiente';
    case PagoRecibido = 'pago_recibido';
    case Confirmado = 'confirmado';
    case Enviado = 'enviado';
    case Cancelado = 'cancelado';

    /**
     * @return list<self>
     */
    public static function activos(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $status): bool => $status !== self::Cancelado && $status !== self::Enviado && $status !== self::Confirmado
        ));
    }

    public function label(): string
    {
        return match ($this) {
            self::Consultando => 'Consultando',
            self::Cotizando => 'Cotizando',
            self::DatosListos => 'Datos listos',
            self::PagoPendiente => 'Pago pendiente',
            self::PagoRecibido => 'Pago recibido',
            self::Confirmado => 'Confirmado',
            self::Enviado => 'Enviado',
            self::Cancelado => 'Cancelado',
        };
    }

    public function puedeConfirmarPago(): bool
    {
        return in_array($this, [self::PagoPendiente, self::PagoRecibido], true);
    }

    public function esPipelineAbierto(): bool
    {
        return ! in_array($this, [self::Cancelado], true);
    }
}
