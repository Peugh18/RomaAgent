<?php

namespace App\Actions\Pedidos;

use App\Enums\SaleStatus;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ReverterPedidoEnviado
{
    public function handle(Sale $sale): Sale
    {
        if ($sale->status !== SaleStatus::Enviado) {
            throw new RuntimeException('Solo se puede revertir un pedido enviado.');
        }

        return DB::transaction(function () use ($sale): Sale {
            $sale->update([
                'status' => SaleStatus::Confirmado,
                'shipped_at' => null,
            ]);

            return $sale->fresh(['customer', 'product', 'productVariant']);
        });
    }
}
