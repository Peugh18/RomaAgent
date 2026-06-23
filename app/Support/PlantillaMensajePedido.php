<?php

namespace App\Support;

use App\Enums\SaleTransitionType;
use App\Models\CompanySetting;
use App\Models\Sale;

class PlantillaMensajePedido
{
    /**
     * @return list<string>
     */
    public static function variablesDisponibles(): array
    {
        return ['{nombre}', '{producto}', '{color}', '{total}', '{metodo_pago}'];
    }

    public static function render(string $plantilla, Sale $sale): string
    {
        $plantilla = ValidadorPlantillaMensaje::normalizar($plantilla);
        if ($plantilla === '') {
            return '';
        }

        $customerData = $sale->customer_data ?? [];
        $nombre = trim((string) (
            $sale->customer?->name
            ?? $customerData['nombre']
            ?? $customerData['name']
            ?? ''
        ));

        if ($sale->relationLoaded('items') && $sale->items->isNotEmpty()) {
            $productNames = $sale->items->map(function ($item) {
                $t = NormalizadorStockTallas::etiquetaPublica((string) $item->size);

                return "{$item->quantity}x {$item->product_name} (talla {$t})";
            })->implode(' y ');
            $color = $sale->items->pluck('color')->filter()->unique()->implode(' y ');
        } else {
            $talla = NormalizadorStockTallas::etiquetaPublica((string) $sale->size);
            $productNames = "{$sale->quantity}x {$sale->product_name} (talla {$talla})";
            $color = (string) $sale->color;
        }

        return str_replace(
            ['{nombre}', '{producto}', '{color}', '{total}', '{metodo_pago}'],
            [
                $nombre,
                $productNames,
                $color,
                number_format((float) $sale->total_amount, 2),
                (string) ($sale->payment_method ?? ''),
            ],
            $plantilla,
        );
    }

    public static function plantillaPara(SaleTransitionType $tipo, ?CompanySetting $settings): string
    {
        $mensajes = $settings?->mensajes;

        $raw = match ($tipo) {
            SaleTransitionType::ConfirmPayment => $mensajes?->pedido_confirmado
                ?: MensajesEmpresaDefaults::pedidoConfirmado(),
            SaleTransitionType::MarkShipped => $mensajes?->pedido_enviado
                ?: MensajesEmpresaDefaults::pedidoEnviado(),
            SaleTransitionType::MarkDelivered => $mensajes?->pedido_entregado
                ?: MensajesEmpresaDefaults::pedidoEntregado(),
        };

        $normalizada = ValidadorPlantillaMensaje::normalizar($raw);

        if ($normalizada !== '') {
            return $normalizada;
        }

        return match ($tipo) {
            SaleTransitionType::ConfirmPayment => MensajesEmpresaDefaults::pedidoConfirmado(),
            SaleTransitionType::MarkShipped => MensajesEmpresaDefaults::pedidoEnviado(),
            SaleTransitionType::MarkDelivered => MensajesEmpresaDefaults::pedidoEntregado(),
        };
    }

    /**
     * @return array<string, string|null>
     */
    public static function resumenPedido(Sale $sale): array
    {
        $customerData = $sale->customer_data ?? [];
        $nombre = trim((string) (
            $sale->customer?->name
            ?? $customerData['nombre']
            ?? $customerData['name']
            ?? null
        ));

        if ($sale->relationLoaded('items') && $sale->items->isNotEmpty()) {
            $productNames = $sale->items->map(function ($item) {
                $t = NormalizadorStockTallas::etiquetaPublica((string) $item->size);

                return "{$item->quantity}x {$item->product_name} (talla {$t})";
            })->implode(' y ');
            $color = $sale->items->pluck('color')->filter()->unique()->implode(' y ');
        } else {
            $talla = NormalizadorStockTallas::etiquetaPublica((string) $sale->size);
            $productNames = "{$sale->quantity}x {$sale->product_name} (talla {$talla})";
            $color = $sale->color;
        }

        return [
            'nombre' => $nombre !== '' ? $nombre : null,
            'producto' => $productNames,
            'color' => $color,
            'total' => $sale->total_amount,
            'metodo_pago' => $sale->payment_method,
        ];
    }

    public static function preview(Sale $sale, SaleTransitionType $tipo, ?CompanySetting $settings): string
    {
        return self::render(self::plantillaPara($tipo, $settings), $sale);
    }
}
