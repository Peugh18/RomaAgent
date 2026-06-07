<?php

namespace App\Console\Commands;

use App\Actions\Mensajes\EnviarMensajeWhatsappSaliente;
use App\Enums\SaleStatus;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Sale;
use Illuminate\Console\Command;

class RecordatoriosPedidoCommand extends Command
{
    protected $signature = 'pedidos:recordatorios';

    protected $description = 'Envía recordatorios 3 y 15 min configurados para pedidos sin respuesta';

    public function handle(EnviarMensajeWhatsappSaliente $enviarMensaje): int
    {
        $settings = CompanySetting::query()->with('mensajes')->first();
        if ($settings === null) {
            return self::SUCCESS;
        }

        $mensajes = $settings->mensajes;
        $msg3 = $mensajes?->recordatorio_3min;
        $msg15 = $mensajes?->recordatorio_15min;
        $msgDatos = $mensajes?->recordatorio_datos;

        Customer::query()
            ->with('activeSale')
            ->whereNotNull('active_sale_id')
            ->whereNotNull('last_inbound_at')
            ->where('ia_paused', false)
            ->chunkById(100, function ($customers) use ($enviarMensaje, $msg3, $msg15, $msgDatos): void {
                foreach ($customers as $customer) {
                    $this->procesarCliente($customer, $enviarMensaje, $msg3, $msg15, $msgDatos);
                }
            });

        return self::SUCCESS;
    }

    private function procesarCliente(
        Customer $customer,
        EnviarMensajeWhatsappSaliente $enviarMensaje,
        ?string $msg3,
        ?string $msg15,
        ?string $msgDatos,
    ): void {
        /** @var Sale|null $sale */
        $sale = $customer->activeSale;

        if ($sale === null) {
            return;
        }

        if (! in_array($sale->status, [
            SaleStatus::Cotizando,
            SaleStatus::DatosListos,
            SaleStatus::PagoPendiente,
        ], true)) {
            return;
        }

        $minutos = $customer->last_inbound_at?->diffInMinutes(now()) ?? 0;

        if ($minutos >= 15 && $customer->reminder_15min_sent_at === null) {
            $mensaje15 = $msgDatos && $this->faltanDatosCliente($sale)
                ? $msgDatos
                : $msg15;

            if ($mensaje15) {
                $enviarMensaje->handle($customer->phone_number, $mensaje15, $customer->name, metadataExtra: [
                    'generated_by' => $msgDatos && $this->faltanDatosCliente($sale)
                        ? 'recordatorio_datos'
                        : 'recordatorio_15min',
                ]);
                $customer->update(['reminder_15min_sent_at' => now()]);
            }

            return;
        }

        if ($minutos >= 3 && $customer->reminder_3min_sent_at === null && $msg3) {
            $enviarMensaje->handle($customer->phone_number, $msg3, $customer->name, metadataExtra: [
                'generated_by' => 'recordatorio_3min',
            ]);
            $customer->update(['reminder_3min_sent_at' => now()]);
        }
    }

    private function faltanDatosCliente(Sale $sale): bool
    {
        $datos = $sale->customer_data;

        return ! is_array($datos) || $datos === [];
    }
}
