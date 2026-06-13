<?php

namespace App\Actions\Pedidos;

use App\Actions\Mensajes\EnviarMensajeWhatsappSaliente;
use App\Enums\SaleStatus;
use App\Models\CompanySetting;
use App\Models\Message;
use App\Models\Sale;
use App\Support\GeneradorLinkPagoTarjeta;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class EnviarLinkPagoTarjeta
{
    public function __construct(
        private EnviarMensajeWhatsappSaliente $enviarMensaje,
    ) {}

    /**
     * @return array{link: string, message: Message}
     */
    public function handle(Sale $sale, ?string $customLink = null): array
    {
        if (! $sale->esPagoTarjeta()) {
            throw new RuntimeException('Este pedido no es pago con tarjeta.');
        }

        if ($sale->status !== SaleStatus::PagoPendiente) {
            throw new RuntimeException('Solo puedes enviar el link cuando el pedido está en pago pendiente.');
        }

        $link = trim((string) $customLink);
        if ($link === '') {
            $settings = CompanySetting::query()->with('ventas')->first();
            $link = GeneradorLinkPagoTarjeta::construir($settings?->ventas, $sale);
        }

        return DB::transaction(function () use ($sale, $link): array {
            $message = $this->enviarMensaje->handle(
                phoneNumber: $sale->phone_number,
                content: $link,
                customerName: $sale->customer?->name,
                metadataExtra: [
                    'generated_by' => 'admin_link_pago_tarjeta',
                    'sale_id' => $sale->id,
                    'type' => 'text',
                ],
            );

            $metadata = is_array($sale->agent_metadata) ? $sale->agent_metadata : [];
            $metadata['link_pago_enviado_at'] = now()->toIso8601String();
            $metadata['link_pago_url'] = $link;
            $sale->update(['agent_metadata' => $metadata]);

            return [
                'link' => $link,
                'message' => $message,
            ];
        });
    }
}
