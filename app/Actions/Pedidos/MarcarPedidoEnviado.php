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

class MarcarPedidoEnviado
{
    public function __construct(
        private EnviarMensajeWhatsappSaliente $enviarMensaje,
    ) {}

    /**
     * @param  list<array{content: string, delay_seconds: int}>  $extraMessages
     */
    public function handle(Sale $sale, string $mensaje, ?string $imageUrl = null, array $extraMessages = []): Sale
    {
        if ($sale->status !== SaleStatus::Confirmado) {
            throw new RuntimeException('Solo se pueden enviar pedidos confirmados.');
        }

        return DB::transaction(function () use ($sale, $mensaje, $imageUrl, $extraMessages): Sale {
            $sale->update([
                'status' => SaleStatus::Enviado,
                'shipped_at' => now(),
            ]);

            $sale = $sale->fresh(['customer', 'product', 'productVariant']);

            $this->enviarMensaje->handle(
                phoneNumber: $sale->phone_number,
                content: $mensaje,
                customerName: $sale->customer?->name,
                imageUrl: $imageUrl,
                metadataExtra: ['generated_by' => 'system_pedido_enviado', 'sale_id' => $sale->id],
            );

            // Send extra bubbles (e.g. delivery reminder for motorizado/shalom) with configured delays
            foreach ($extraMessages as $extra) {
                if (! empty($extra['content'])) {
                    $this->enviarMensaje->handle(
                        phoneNumber: $sale->phone_number,
                        content: $extra['content'],
                        customerName: $sale->customer?->name,
                        delaySeconds: (int) ($extra['delay_seconds'] ?? 0),
                        metadataExtra: ['generated_by' => 'system_pedido_enviado_extra', 'sale_id' => $sale->id],
                    );
                }
            }

            return $sale;
        });
    }

    public static function mensajePorDefecto(Sale $sale): string
    {
        $settings = CompanySetting::query()->with('mensajes')->first();

        return PlantillaMensajePedido::preview($sale, SaleTransitionType::MarkShipped, $settings);
    }
}
