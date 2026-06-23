<?php

namespace App\Support;

use App\Models\Sale;

/**
 * Formatea el pedido activo como bloque de contexto para el agente IA.
 */
class ContextoPedidoActivo
{
    public static function formatear(?Sale $sale, string $moneda = 'PEN'): string
    {
        if ($sale === null) {
            return <<<'BLOQUE'
## PEDIDO ACTIVO (FUENTE DE VERDAD)
No hay pedido en curso para esta clienta.
Si confirma compra, llama **actualizar_pedido** con producto, color, cantidad y precios.
BLOQUE;
        }

        $simbolo = FormateadorCatalogoProductos::simboloDesdeMoneda($moneda);
        $total = (float) $sale->total_amount;

        $lineasDesglose = [];
        $totalQuantity = 0;

        if ($sale->items && $sale->items->isNotEmpty()) {
            // Verificar si el único item es el placeholder por defecto
            if ($sale->items->count() === 1 && $sale->items->first()->product_name === 'Pedido' && (float) $sale->items->first()->unit_price === 0.0) {
                $lineasDesglose[] = '**PRODUCTOS EN EL CARRITO:**';
                $lineasDesglose[] = '- (Vacío - Esperando confirmación de prendas por parte del cliente)';
            } else {
                $lineasDesglose[] = '**PRODUCTOS EN EL CARRITO:**';
                foreach ($sale->items as $item) {
                    $qty = max(1, (int) $item->quantity);
                    $totalQuantity += $qty;
                    $talla = NormalizadorStockTallas::etiquetaPublica($item->size);
                    $producto = trim((string) $item->product_name) !== '' ? $item->product_name : 'Sin producto';
                    $color = trim((string) ($item->color ?? '')) !== '' ? $item->color : 'Sin color';
                    $unitPrice = (float) $item->unit_price;

                    $lineasDesglose[] = sprintf('- %dx %s | Color: %s | %s | Unitario: %s %.2f',
                        $qty, $producto, $color, $talla, $simbolo, $unitPrice);
                }
            }
        } else {
            // Fallback legacy
            if ($sale->product_name === 'Pedido' && (float) $sale->unit_price === 0.0) {
                $lineasDesglose[] = '**PRODUCTOS EN EL CARRITO:**';
                $lineasDesglose[] = '- (Vacío - Esperando confirmación de prendas por parte del cliente)';
            } else {
                $qty = max(1, (int) $sale->quantity);
                $totalQuantity += $qty;
                $talla = NormalizadorStockTallas::etiquetaPublica($sale->size);
                $producto = trim((string) $sale->product_name) !== '' ? $sale->product_name : 'Sin producto';
                $color = trim((string) ($sale->color ?? '')) !== '' ? $sale->color : 'Sin color';
                $unitPrice = (float) $sale->unit_price;

                $lineasDesglose[] = '**PRODUCTO EN EL CARRITO:**';
                $lineasDesglose[] = sprintf('- %dx %s | Color: %s | %s | Unitario: %s %.2f',
                    $qty, $producto, $color, $talla, $simbolo, $unitPrice);
            }
        }

        $lineasDesglose[] = '';
        $lineasDesglose[] = sprintf('- **TOTAL A COBRAR: %s %.2f**', $simbolo, $total);
        $lineasDesglose[] = sprintf('- Estado: %s', $sale->status->value);
        $lineasDesglose[] = sprintf('- Método pago: %s', $sale->payment_method ?: 'Sin definir');

        $datosCliente = $sale->customer_data ?? [];
        if ($datosCliente !== []) {
            $lineasDesglose[] = '- Datos clienta: '.json_encode($datosCliente, JSON_UNESCAPED_UNICODE);
        }

        $cuerpo = implode("\n", $lineasDesglose);

        return <<<BLOQUE
## PEDIDO ACTIVO (FUENTE DE VERDAD — OBLIGATORIO)
Usa EXACTAMENTE estos datos al hablar de cantidades, precios y totales. No recalcules de memoria ni ignores la cantidad.

{$cuerpo}

**Reglas:**
- Si la clienta cambia cantidad o color, llama **actualizar_pedido** ANTES de decirle el nuevo total.
- El total que escribes al cliente DEBE ser **{$simbolo} {$total}** (total artículos: {$totalQuantity}).
- Producto y color ya confirmados: no volver a pedirlos salvo que la clienta quiera cambiar.
BLOQUE;
    }
}
