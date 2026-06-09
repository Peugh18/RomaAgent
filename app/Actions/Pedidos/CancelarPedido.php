<?php

namespace App\Actions\Pedidos;

use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CancelarPedido
{
    public function handle(Sale $sale): Sale
    {
        if (! $sale->puedeCancelar()) {
            throw new RuntimeException('Este pedido no puede cancelarse en su estado actual.');
        }

        return DB::transaction(function () use ($sale): Sale {
            $sale->cancelar();

            $customer = $sale->customer;
            if ($customer !== null) {
                if ($customer->active_sale_id === $sale->id) {
                    $customer->update(['active_sale_id' => null]);
                }

                $customer->reanudarIa();
            }

            return $sale->fresh(['customer', 'product', 'productVariant']);
        });
    }
}
