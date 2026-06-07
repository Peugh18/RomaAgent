<?php

namespace App\Enums;

enum SaleTransitionType: string
{
    case ConfirmPayment = 'confirm_payment';
    case MarkShipped = 'mark_shipped';
    case MarkDelivered = 'mark_delivered';

    public function label(): string
    {
        return match ($this) {
            self::ConfirmPayment => 'Confirmar pago',
            self::MarkShipped => 'Marcar enviado',
            self::MarkDelivered => 'Marcar entregado (activar bot)',
        };
    }
}
