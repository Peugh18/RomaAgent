<?php

namespace App\Actions\Pedidos;

use App\Enums\SaleStatus;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Support\NormalizadorStockTallas;
use App\Support\ValidadorPrecioPedido;
use Illuminate\Support\Facades\DB;

class ActualizarPedidoVenta
{
    /**
     * @param  array<string, mixed>  $datos
     */
    public function handle(Customer $customer, array $datos): Sale
    {
        return DB::transaction(function () use ($customer, $datos): Sale {
            $sale = $this->resolverPedidoActivo($customer);

            $status = isset($datos['status'])
                ? SaleStatus::from($datos['status'])
                : ($sale->exists ? $sale->status : SaleStatus::Cotizando);

            $deliveryCost = isset($datos['delivery_cost'])
                ? max(0, (float) $datos['delivery_cost'])
                : (float) $sale->delivery_cost;

            $itemsData = $datos['items'] ?? [];
            if (empty($itemsData) && isset($datos['product_name'])) {
                // Fallback para array de single product si manda formato antiguo
                $itemsData = [[
                    'product_name' => $datos['product_name'],
                    'color' => $datos['color'] ?? null,
                    'size' => $datos['size'] ?? null,
                    'quantity' => $datos['quantity'] ?? 1,
                    'unit_price' => $datos['unit_price'] ?? null,
                ]];
            }

            // Calcular items
            $processedItems = [];
            $totalSubtotal = 0;
            $firstProduct = null;
            $firstVariant = null;

            foreach ($itemsData as $idx => $itemData) {
                $productName = $itemData['product_name'] ?? 'Producto';
                $product = $this->resolverProducto($productName);
                $variant = $this->resolverVariante($product, $itemData['color'] ?? null);

                if ($idx === 0) {
                    $firstProduct = $product;
                    $firstVariant = $variant;
                }

                $unitPrice = ValidadorPrecioPedido::resolverPrecioUnitario(
                    $product,
                    $itemData['unit_price'] ?? null,
                );
                
                $quantity = max(1, (int) ($itemData['quantity'] ?? 1));
                $subtotal = $unitPrice * $quantity;
                $totalSubtotal += $subtotal;

                $processedItems[] = [
                    'product_id' => $product?->id,
                    'product_variant_id' => $variant?->id,
                    'product_name' => $product?->name ?? $productName,
                    'color' => $variant?->color ?? ($itemData['color'] ?? null),
                    'size' => strtoupper(trim((string) ($itemData['size'] ?? NormalizadorStockTallas::defaultSizeKey()))),
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                ];
            }

            // Si hay items nuevos en $datos, recalculamos el total. Si no mandó items, usamos el existente pero recalculamos total por si cambió el envío
            if (!empty($processedItems)) {
                $total = $totalSubtotal + $deliveryCost;
            } else {
                $totalSubtotal = $sale->items()->sum('subtotal');
                $total = $totalSubtotal + $deliveryCost;
            }

            // Opcional: el bot podría enviar total_amount forzoso
            if (isset($datos['total_amount'])) {
                $total = (float) $datos['total_amount'];
            }

            $payload = [
                'phone_number' => $customer->phone_number,
                // Mantener los campos de legacy en la venta para el primer producto (compatibilidad vista antigua/migraciones)
                'product_id' => $firstProduct?->id ?? $sale->product_id,
                'product_variant_id' => $firstVariant?->id ?? $sale->product_variant_id,
                'product_name' => $processedItems[0]['product_name'] ?? $sale->product_name,
                'color' => $processedItems[0]['color'] ?? $sale->color,
                'size' => $processedItems[0]['size'] ?? $sale->size ?? NormalizadorStockTallas::defaultSizeKey(),
                'quantity' => $processedItems[0]['quantity'] ?? $sale->quantity ?? 1,
                'unit_price' => $processedItems[0]['unit_price'] ?? $sale->unit_price ?? 0,
                
                'delivery_cost' => $deliveryCost,
                'total_amount' => $total,
                'payment_method' => $datos['payment_method'] ?? $sale->payment_method,
                'delivery_type' => $datos['delivery_type'] ?? $sale->delivery_type,
                'delivery_district' => $datos['delivery_district'] ?? $sale->delivery_district,
                'status' => $status,
                'customer_data' => array_merge($sale->customer_data ?? [], $datos['customer_data'] ?? []),
                'notes' => $datos['notes'] ?? $sale->notes,
            ];

            if ($sale->exists) {
                $sale->update($payload);
            } else {
                $payload['customer_id'] = $customer->id;
                $sale = Sale::query()->create($payload);
            }

            // Sincronizar items (Borrar y recrear es más seguro para el carrito del bot)
            if (!empty($processedItems)) {
                $sale->items()->delete();
                foreach ($processedItems as $item) {
                    $sale->items()->create($item);
                }
            } elseif ($sale->wasRecentlyCreated) {
                // Caso extremo: se creó venta sin items. Agregamos un item por defecto
                $sale->items()->create([
                    'product_name' => $sale->product_name,
                    'quantity' => $sale->quantity,
                    'unit_price' => $sale->unit_price,
                    'subtotal' => $sale->unit_price * $sale->quantity,
                ]);
            }

            // Update customer name from customer_data if provided
            $customerName = $datos['customer_data']['nombre_completo']
                ?? $datos['customer_data']['nombre']
                ?? $datos['customer_data']['name']
                ?? null;
            if ($customerName !== null && trim($customerName) !== '' && $customer->name !== $customerName) {
                $customer->update(['name' => trim($customerName)]);
            }

            $customer->asignarPedidoActivo($sale);

            return $sale->fresh(['product', 'productVariant', 'items']);
        });
    }

    private function resolverPedidoActivo(Customer $customer): Sale
    {
        if ($customer->active_sale_id !== null) {
            $existing = Sale::query()->find($customer->active_sale_id);
            if ($existing !== null && $existing->estaAbierto()) {
                return $existing;
            }
        }

        $open = Sale::query()
            ->where('customer_id', $customer->id)
            ->whereNotIn('status', [
                SaleStatus::Confirmado,
                SaleStatus::Enviado,
                SaleStatus::Entregado,
                SaleStatus::Cancelado,
            ])
            ->latest()
            ->first();

        if ($open !== null) {
            return $open;
        }

        return new Sale([
            'customer_id' => $customer->id,
            'phone_number' => $customer->phone_number,
            'product_name' => 'Pedido',
            'size' => NormalizadorStockTallas::defaultSizeKey(),
            'quantity' => 1,
            'unit_price' => 0,
            'delivery_cost' => 0,
            'total_amount' => 0,
            'status' => SaleStatus::Consultando,
        ]);
    }

    private function resolverProducto(?string $name): ?Product
    {
        if ($name === null || trim($name) === '') {
            return null;
        }

        $normalized = trim($name);

        return Product::query()
            ->where('status', Product::ESTADO_DISPONIBLE)
            ->where(function ($query) use ($normalized): void {
                $query->where('name', 'like', "%{$normalized}%")
                    ->orWhere('name', $normalized);
            })
            ->with('variants')
            ->first();
    }

    private function resolverVariante(?Product $product, ?string $color): ?ProductVariant
    {
        if ($product === null || $color === null || trim($color) === '') {
            return null;
        }

        $needle = mb_strtolower(trim($color));

        return $product->variants->first(
            fn (ProductVariant $variant): bool => mb_strtolower($variant->color) === $needle
                || str_contains(mb_strtolower($variant->color), $needle)
        );
    }
}
