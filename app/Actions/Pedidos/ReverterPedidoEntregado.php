<?php

namespace App\Actions\Pedidos;

use App\Enums\SaleStatus;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ReverterPedidoEntregado
{
    public function handle(Sale $sale): Sale
    {
        if ($sale->status !== SaleStatus::Entregado) {
            throw new RuntimeException('Solo se puede revertir un pedido entregado.');
        }

        return DB::transaction(function () use ($sale): Sale {
            $sale->update([
                'status' => SaleStatus::Enviado,
                'delivered_at' => null,
            ]);

            $customer = $sale->customer;
            if ($customer !== null && ! $customer->ia_paused) {
                $customer->pausarIa('Pedido reabierto: pendiente de entrega');
            }

            return $sale->fresh(['customer', 'product', 'productVariant']);
        });
    }
}
