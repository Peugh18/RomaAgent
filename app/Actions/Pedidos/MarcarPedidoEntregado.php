<?php

namespace App\Actions\Pedidos;

use App\Actions\Mensajes\EnviarMensajeWhatsappSaliente;
use App\Enums\SaleStatus;
use App\Enums\SaleTransitionType;
use App\Models\CompanySetting;
use App\Models\Sale;
use App\Support\PlantillaMensajePedido;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MarcarPedidoEntregado
{
    public function __construct(
        private EnviarMensajeWhatsappSaliente $enviarMensaje,
    ) {}

    public function handle(Sale $sale, string $mensaje): Sale
    {
        if ($sale->status !== SaleStatus::Enviado) {
            throw new RuntimeException('Solo se pueden entregar pedidos enviados.');
        }

        return DB::transaction(function () use ($sale, $mensaje): Sale {
            $sale->update([
                'status' => SaleStatus::Entregado,
                'delivered_at' => now(),
            ]);

            $customer = $sale->customer;

            if ($customer !== null) {
                if ($customer->active_sale_id === $sale->id) {
                    $customer->update(['active_sale_id' => null]);
                }

                $customer->reanudarIa();
            }

            $sale = $sale->fresh(['customer', 'product', 'productVariant']);

            $this->enviarMensaje->handle(
                phoneNumber: $sale->phone_number,
                content: $mensaje,
                customerName: $sale->customer?->name,
                metadataExtra: ['generated_by' => 'system_pedido_entregado', 'sale_id' => $sale->id],
            );

            return $sale;
        });
    }

    public static function mensajePorDefecto(Sale $sale): string
    {
        $settings = CompanySetting::query()->with('mensajes')->first();

        return PlantillaMensajePedido::preview($sale, SaleTransitionType::MarkDelivered, $settings);
    }
}
