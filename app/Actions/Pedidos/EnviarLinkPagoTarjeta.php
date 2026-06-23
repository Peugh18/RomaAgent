<?php

namespace App\Actions\Pedidos;

use App\Actions\Mensajes\EnviarMensajeWhatsappSaliente;
use App\Enums\SaleStatus;
use App\Models\Sale;
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

        $sale->loadMissing('items');

        $linkUrl = trim((string) $customLink);
        if ($linkUrl === '') {
            $settings = CompanySetting::query()->with('ventas')->first();
            $linkUrl = GeneradorLinkPagoTarjeta::construir($settings?->ventas, $sale);
        }

        $listaProductos = '';

        if ($sale->items->isNotEmpty()) {
            foreach ($sale->items as $item) {
                $nombreItem = $item->product_name;
                if ($item->color || $item->size) {
                    $variante = collect([$item->color, $item->size])->filter()->join(' / ');
                    $nombreItem .= " ($variante)";
                }
                $precioFmt = number_format((float) $item->unit_price, 2, '.', '');
                $listaProductos .= "- {$item->quantity} x {$nombreItem} (S/ {$precioFmt})\n";
            }
        } else {
            $nombreItem = $sale->product_name;
            if ($sale->color || $sale->size) {
                $variante = collect([$sale->color, $sale->size])->filter()->join(' / ');
                $nombreItem .= " ($variante)";
            }
            $precioFmt = number_format((float) $sale->unit_price, 2, '.', '');
            $listaProductos .= "- {$sale->quantity} x {$nombreItem} (S/ {$precioFmt})\n";
        }
        $totalOriginal = (float) $sale->total_amount;
        $recargoTarjeta = round($totalOriginal * 0.05, 2);
        $totalAPagar = $totalOriginal + $recargoTarjeta;

        $recargoFmt = number_format($recargoTarjeta, 2, '.', '');
        $totalFinalFmt = number_format($totalAPagar, 2, '.', '');

        $mensajeFinal = "¡Hola hermosa! Aquí tienes el resumen de tu pedido:\n\n"
            ."🛍️ *Tus productos:*\n"
            ."{$listaProductos}"
            ."-------------------------\n"
            ."💳 *Recargo por tarjeta (5%):* S/ {$recargoFmt}\n"
            ."=========================\n"
            ."💰 *TOTAL A PAGAR:* S/ {$totalFinalFmt}\n\n"
            ."🔗 *Tu link de pago seguro es:*\n"
            ."{$linkUrl}\n\n"
            .'✅ Por favor, envíame tu comprobante por aquí mismo una vez realizado el pago para confirmar tu pedido.';

        return DB::transaction(function () use ($sale, $linkUrl, $mensajeFinal): array {
            $message = $this->enviarMensaje->handle(
                phoneNumber: $sale->phone_number,
                content: $mensajeFinal,
                customerName: $sale->customer?->name,
                metadataExtra: [
                    'generated_by' => 'admin_link_pago_tarjeta',
                    'sale_id' => $sale->id,
                    'type' => 'text',
                ],
            );

            $metadata = is_array($sale->agent_metadata) ? $sale->agent_metadata : [];
            $metadata['link_pago_enviado_at'] = now()->toIso8601String();
            $metadata['link_pago_url'] = $linkUrl;
            $sale->update(['agent_metadata' => $metadata]);

            if ($sale->customer) {
                $sale->customer->reanudarIa();
            }

            return [
                'link' => $linkUrl,
                'message' => $message,
            ];
        });
    }
}
