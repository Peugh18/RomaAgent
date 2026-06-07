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
Si confirma compra, llama **actualizar_pedido** con producto, color, cantidad, precios y envío.
BLOQUE;
        }

        $simbolo = FormateadorCatalogoProductos::simboloDesdeMoneda($moneda);
        $quantity = max(1, (int) $sale->quantity);
        $unitPrice = (float) $sale->unit_price;
        $deliveryCost = (float) $sale->delivery_cost;
        $total = (float) $sale->total_amount;
        $subtotal = $unitPrice * $quantity;
        $talla = NormalizadorStockTallas::etiquetaPublica($sale->size);

        $producto = trim((string) $sale->product_name) !== '' ? $sale->product_name : 'Sin producto';
        $color = trim((string) ($sale->color ?? '')) !== '' ? $sale->color : 'Sin color';
        $envio = trim((string) ($sale->delivery_type ?? '')) !== ''
            ? $sale->delivery_type.($sale->delivery_district ? " ({$sale->delivery_district})" : '')
            : 'Sin definir';

        $lineasDesglose = [
            sprintf('- Producto: %s | Color: %s | %s', $producto, $color, $talla),
            sprintf('- Cantidad: %d unidad(es)', $quantity),
            sprintf('- Precio unitario: %s %.2f', $simbolo, $unitPrice),
            sprintf('- Subtotal producto: %s %.2f (%d × %s %.2f)', $simbolo, $subtotal, $quantity, $simbolo, $unitPrice),
            sprintf('- Costo envío: %s %.2f', $simbolo, $deliveryCost),
            sprintf('- **TOTAL A COBRAR: %s %.2f**', $simbolo, $total),
            sprintf('- Estado: %s', $sale->status->value),
            sprintf('- Método pago: %s', $sale->payment_method ?: 'Sin definir'),
            sprintf('- Envío: %s', $envio),
        ];

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
- Si la clienta cambia cantidad, color o envío, llama **actualizar_pedido** ANTES de decirle el nuevo total.
- El total que escribes al cliente DEBE ser **{$simbolo} {$total}** (cantidad {$quantity}, no asumas 1 unidad).
- Producto y color ya confirmados: no volver a pedirlos salvo que la clienta quiera cambiar.
BLOQUE;
    }
}
