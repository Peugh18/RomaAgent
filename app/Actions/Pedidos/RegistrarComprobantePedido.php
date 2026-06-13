<?php

namespace App\Actions\Pedidos;

use App\Enums\SaleStatus;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Message;
use App\Models\Sale;
use App\Support\ComprobantePagoMensaje;
use App\Support\HorarioAtencionEmpresa;
use App\Support\MensajesEmpresaDefaults;
use Illuminate\Support\Facades\DB;

class RegistrarComprobantePedido
{
    /**
     * @param  array<string, mixed>  $args
     * @return array{mensaje: string, sale: Sale|null, pausado: bool}
     */
    public function handle(Customer $customer, ?Message $mensajeComprobante = null, array $args = []): array
    {
        return DB::transaction(function () use ($customer, $mensajeComprobante, $args): array {
            $sale = $customer->activeSale;

            if ($sale === null) {
                $sale = Sale::query()
                    ->where('customer_id', $customer->id)
                    ->whereNotIn('status', [
                        SaleStatus::Confirmado,
                        SaleStatus::Enviado,
                        SaleStatus::Entregado,
                        SaleStatus::Cancelado,
                    ])
                    ->latest()
                    ->first();
            }

            if ($sale !== null) {
                $sale->marcarPagoRecibido();

                if (! empty($args['payment_method']) && empty($sale->payment_method)) {
                    $sale->update(['payment_method' => $args['payment_method']]);
                }

                if ($mensajeComprobante !== null && ComprobantePagoMensaje::esImagenEntrante($mensajeComprobante)) {
                    ComprobantePagoMensaje::marcar($mensajeComprobante, $sale->fresh());
                }
            }

            $settings = CompanySetting::query()->with(['mensajes', 'horarios'])->first();
            $enHorario = HorarioAtencionEmpresa::estaEnHorario($settings?->horarios?->horario_atencion ?? $settings?->horario_atencion);

            $mensajes = $settings?->mensajes;
            $mensaje = $enHorario
                ? ($mensajes?->comprobante_recibido ?: $settings?->mensaje_comprobante_recibido ?: MensajesEmpresaDefaults::comprobanteRecibido())
                : ($mensajes?->comprobante_fuera_horario ?: $settings?->mensaje_comprobante_fuera_horario ?: MensajesEmpresaDefaults::comprobanteFueraHorario());

            $customer->pausarIa('Comprobante de pago por revisar');

            return [
                'mensaje' => $mensaje,
                'sale' => $sale?->fresh(),
                'pausado' => true,
            ];
        });
    }
}
