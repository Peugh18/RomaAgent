<?php

namespace App\Actions\Pedidos;

use App\Actions\Mensajes\EnviarMensajeWhatsappSaliente;
use App\Enums\SaleStatus;
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

    /**
     * @param  list<array{content: string, delay_seconds: int}>  $extraMessages
     */
    public function handle(Sale $sale, User $user, string $mensaje, array $extraMessages = []): Sale
    {
        if (! $sale->puedeVerificarPago()) {
            throw new RuntimeException(
                'Este pedido no puede verificarse en estado: '.$sale->status->label()
            );
        }

        return DB::transaction(function () use ($sale, $user, $mensaje, $extraMessages): Sale {
            if ($sale->items->isNotEmpty()) {
                foreach ($sale->items as $item) {
                    $this->servicioStock->decrementarPorVentaConfirmada(
                        $item->product_variant_id,
                        $item->size,
                        $item->quantity,
                    );
                }
            } else {
                $this->servicioStock->decrementarPorVentaConfirmada(
                    $sale->product_variant_id,
                    $sale->size,
                    $sale->quantity,
                );
            }

            $sale->update([
                'status' => SaleStatus::Confirmado,
                'confirmed_at' => now(),
                'confirmed_by_user_id' => $user->id,
            ]);

            $sale = $sale->fresh(['customer', 'product', 'productVariant', 'confirmedByUser']);

            $this->enviarMensaje->handle(
                phoneNumber: $sale->phone_number,
                content: $mensaje,
                customerName: $sale->customer?->name,
                metadataExtra: ['generated_by' => 'system_confirmacion_pago', 'sale_id' => $sale->id],
            );

            // Send extra bubbles (e.g. "Pedido por preparar" summary) with configured delays
            foreach ($extraMessages as $extra) {
                if (! empty($extra['content'])) {
                    $this->enviarMensaje->handle(
                        phoneNumber: $sale->phone_number,
                        content: $extra['content'],
                        customerName: $sale->customer?->name,
                        delaySeconds: (int) ($extra['delay_seconds'] ?? 0),
                        metadataExtra: ['generated_by' => 'system_confirmacion_extra', 'sale_id' => $sale->id],
                    );
                }
            }

            $customer = $sale->customer;
            if ($customer !== null) {
                $customer->pausarIa('Pedido confirmado: modo humano activo');
            }

            return $sale;
        });
    }
}
