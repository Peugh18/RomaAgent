<?php

namespace App\Actions\Pedidos;

use App\Actions\Mensajes\EnviarMensajeWhatsappSaliente;
use App\Enums\SaleStatus;
use App\Models\CompanySetting;
use App\Support\MensajesEmpresaDefaults;
use App\Models\Sale;
use App\Models\User;
use App\Services\Pedidos\ServicioStockPedido;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ConfirmarPagoPedido
{
    public function __construct(
        private ServicioStockPedido $servicioStock,
        private EnviarMensajeWhatsappSaliente $enviarMensaje,
    ) {}

    public function handle(Sale $sale, User $user): Sale
    {
        if (! $sale->status->puedeConfirmarPago()) {
            throw new RuntimeException(
                'Este pedido no puede confirmarse en estado: '.$sale->status->label()
            );
        }

        return DB::transaction(function () use ($sale, $user): Sale {
            $this->servicioStock->decrementarPorVentaConfirmada(
                $sale->product_variant_id,
                $sale->size,
                $sale->quantity,
            );

            $sale->update([
                'status' => SaleStatus::Confirmado,
                'confirmed_at' => now(),
                'confirmed_by_user_id' => $user->id,
            ]);

            $customer = $sale->customer;
            if ($customer !== null && $customer->active_sale_id === $sale->id) {
                $customer->update(['active_sale_id' => null]);
            }

            if ($customer !== null && $customer->ia_paused) {
                $customer->reanudarIa();
            }

            $sale = $sale->fresh(['customer', 'product', 'productVariant', 'confirmedByUser']);

            $this->notificarClientePedidoConfirmado($sale);

            return $sale;
        });
    }

    private function notificarClientePedidoConfirmado(Sale $sale): void
    {
        $settings = CompanySetting::query()->first();
        $plantilla = $settings?->mensaje_pedido_confirmado
            ?: MensajesEmpresaDefaults::pedidoConfirmado();

        $mensaje = str_replace(
            ['{producto}', '{color}', '{total}'],
            [$sale->product_name, (string) $sale->color, number_format((float) $sale->total_amount, 2)],
            $plantilla
        );

        $this->enviarMensaje->handle(
            phoneNumber: $sale->phone_number,
            content: $mensaje,
            customerName: $sale->customer?->name,
            metadataExtra: ['generated_by' => 'system_confirmacion_pago', 'sale_id' => $sale->id],
        );
    }
}
