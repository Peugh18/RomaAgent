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

            // Handle items
            $items = $datos['items'] ?? [];
            if (! empty($items) && is_array($items)) {
                $firstItem = $items[0];
                $datos['product_name'] = $firstItem['product_name'] ?? $datos['product_name'] ?? null;
                $datos['color'] = $firstItem['color'] ?? $datos['color'] ?? null;
                $datos['size'] = $firstItem['size'] ?? $datos['size'] ?? null;
                $datos['quantity'] = $firstItem['quantity'] ?? $datos['quantity'] ?? null;
                $datos['unit_price'] = $firstItem['unit_price'] ?? $datos['unit_price'] ?? null;
            }

            $product = $this->resolverProducto($datos['product_name'] ?? $sale->product_name);
            $variant = $this->resolverVariante($product, $datos['color'] ?? $sale->color);

            $unitPrice = ValidadorPrecioPedido::resolverPrecioUnitario(
                $product,
                $datos['unit_price'] ?? null,
            );

            $deliveryCost = isset($datos['delivery_cost'])
                ? max(0, (float) $datos['delivery_cost'])
                : (float) $sale->delivery_cost;

            $quantity = max(1, (int) ($datos['quantity'] ?? $sale->quantity));

            $newStatus = isset($datos['status']) ? SaleStatus::from($datos['status']) : null;
            
            // Prevent downgrading the status if it's already PagoRecibido or further
            if ($newStatus === SaleStatus::DatosListos || $newStatus === SaleStatus::Cotizando || $newStatus === SaleStatus::Consultando) {
                if ($sale->exists && in_array($sale->status, [SaleStatus::PagoPendiente, SaleStatus::PagoRecibido, SaleStatus::Confirmado, SaleStatus::Enviado, SaleStatus::Entregado], true)) {
                    $status = $sale->status; // Keep current advanced status
                } else {
                    $status = $newStatus ?? ($sale->exists ? $sale->status : SaleStatus::Cotizando);
                }
            } else {
                $status = $newStatus ?? ($sale->exists ? $sale->status : SaleStatus::Cotizando);
            }

            // Calculate total correctly by summing up all items if they exist
            $itemsToCalculate = (! empty($items) && is_array($items)) ? $items : ($sale->exists ? $sale->items->map(fn($i) => ['product_name' => $i->product_name, 'quantity' => $i->quantity, 'unit_price' => $i->unit_price])->toArray() : []);
            
            if (! empty($itemsToCalculate) && is_array($itemsToCalculate)) {
                $itemsSubtotal = 0;
                $totalQuantity = 0;
                foreach ($itemsToCalculate as $itemData) {
                    $itemProduct = $this->resolverProducto($itemData['product_name'] ?? null);
                    $itemQty = max(1, (int) ($itemData['quantity'] ?? 1));
                    $itemPrice = ValidadorPrecioPedido::resolverPrecioUnitario($itemProduct, $itemData['unit_price'] ?? null);
                    $itemsSubtotal += ($itemQty * $itemPrice);
                    $totalQuantity += $itemQty;
                }
                $quantity = $totalQuantity > 0 ? $totalQuantity : $quantity;
                $total = $itemsSubtotal + $deliveryCost;
            } else {
                $total = ValidadorPrecioPedido::calcularTotal($unitPrice, $quantity, $deliveryCost);
            }

            $payload = [
                'phone_number' => $customer->phone_number,
                'product_id' => $product?->id,
                'product_variant_id' => $variant?->id,
                'product_name' => $product?->name ?? (string) ($datos['product_name'] ?? $sale->product_name),
                'color' => $variant?->color ?? ($datos['color'] ?? $sale->color),
                'size' => NormalizadorStockTallas::esTallaEstandar((string) ($datos['size'] ?? $sale->size))
                    ? NormalizadorStockTallas::defaultSizeKey()
                    : mb_strtoupper(trim((string) ($datos['size'] ?? $sale->size)), 'UTF-8'),
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
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

            // Update customer name from customer_data if provided
            $customerName = $datos['customer_data']['nombre']
                ?? $datos['customer_data']['name']
                ?? null;
            if ($customerName !== null && trim($customerName) !== '' && $customer->name !== $customerName) {
                $customer->update(['name' => trim($customerName)]);
            }

            $customer->asignarPedidoActivo($sale);

            // Sync SaleItems if provided
            if (! empty($items) && is_array($items)) {
                // Filtrar el placeholder "Pedido" que puede enviar el AI si lee el carrito vacío
                $itemsReales = array_filter($items, function ($itemData) {
                    $name = $itemData['product_name'] ?? '';
                    $price = (float)($itemData['unit_price'] ?? 0);
                    return !($name === 'Pedido' && $price === 0.0);
                });

                if (!empty($itemsReales) || empty($sale->items) || $sale->items->isEmpty()) {
                    $sale->items()->delete();
                    foreach ($itemsReales as $itemData) {
                        $itemProduct = $this->resolverProducto($itemData['product_name'] ?? null);
                        $itemVariant = $this->resolverVariante($itemProduct, $itemData['color'] ?? null);

                        $itemQty = max(1, (int) ($itemData['quantity'] ?? 1));
                        $itemPrice = ValidadorPrecioPedido::resolverPrecioUnitario($itemProduct, $itemData['unit_price'] ?? null);

                        $sale->items()->create([
                            'product_id' => $itemProduct?->id,
                            'product_variant_id' => $itemVariant?->id,
                            'product_name' => $itemProduct?->name ?? (string) ($itemData['product_name'] ?? 'Pedido'),
                            'color' => $itemVariant?->color ?? ($itemData['color'] ?? null),
                            'size' => NormalizadorStockTallas::esTallaEstandar((string) ($itemData['size'] ?? ''))
                                ? NormalizadorStockTallas::defaultSizeKey()
                                : mb_strtoupper(trim((string) ($itemData['size'] ?? '')), 'UTF-8'),
                            'quantity' => $itemQty,
                            'unit_price' => $itemPrice,
                            'subtotal' => $itemQty * $itemPrice,
                        ]);
                    }
                }
            }

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
