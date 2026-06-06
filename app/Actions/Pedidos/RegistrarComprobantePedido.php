<?php

namespace App\Actions\Pedidos;

use App\Models\CompanySetting;
use App\Support\MensajesEmpresaDefaults;
use App\Models\Customer;
use App\Models\Sale;
use App\Support\HorarioAtencionEmpresa;
use Illuminate\Support\Facades\DB;

class RegistrarComprobantePedido
{
    /**
     * @return array{mensaje: string, sale: Sale|null, pausado: bool}
     */
    public function handle(Customer $customer): array
    {
        return DB::transaction(function () use ($customer): array {
            $sale = $customer->activeSale;

            if ($sale === null) {
                $sale = Sale::query()
                    ->where('customer_id', $customer->id)
                    ->latest()
                    ->first();
            }

            if ($sale !== null) {
                $sale->marcarPagoRecibido();
            }

            $settings = CompanySetting::query()->first();
            $enHorario = HorarioAtencionEmpresa::estaEnHorario($settings?->horario_atencion);

            $mensaje = $enHorario
                ? ($settings?->mensaje_comprobante_recibido ?: MensajesEmpresaDefaults::comprobanteRecibido())
                : ($settings?->mensaje_comprobante_fuera_horario ?: MensajesEmpresaDefaults::comprobanteFueraHorario());

            $customer->pausarIa('Comprobante de pago por revisar');

            return [
                'mensaje' => $mensaje,
                'sale' => $sale?->fresh(),
                'pausado' => true,
            ];
        });
    }
}
