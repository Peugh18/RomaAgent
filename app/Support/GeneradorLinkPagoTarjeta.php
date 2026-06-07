<?php

namespace App\Support;

use App\Models\Sale;
use App\Models\VentaConfig;
use RuntimeException;

class GeneradorLinkPagoTarjeta
{
    public static function construir(?VentaConfig $venta, Sale $sale): string
    {
        $base = trim((string) ($venta?->link_pago_tarjeta ?? ''));
        if ($base === '') {
            throw new RuntimeException(
                'Configura el link de pago con tarjeta en Configuración → Pagos → Link de pago tarjeta.'
            );
        }

        $total = (float) $sale->total_amount;

        return str_replace(
            ['{total}', '{sale_id}', '{telefono}', '{producto}'],
            [
                number_format($total, 2, '.', ''),
                (string) $sale->id,
                (string) $sale->phone_number,
                (string) $sale->product_name,
            ],
            $base,
        );
    }
}
