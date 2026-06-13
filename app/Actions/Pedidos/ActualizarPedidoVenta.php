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

            $status = isset($datos['status'])
                ? SaleStatus::from($datos['status'])
                : ($sale->exists ? $sale->status : SaleStatus::Cotizando);

            $total = ValidadorPrecioPedido::calcularTotal($unitPrice, $quantity, $deliveryCost);

            $payload = [
                'phone_number' => $customer->phone_number,
                'product_id' => $product?->id,
                'product_variant_id' => $variant?->id,
                'product_name' => $product?->name ?? (string) ($datos['product_name'] ?? $sale->product_name),
                'color' => $variant?->color ?? ($datos['color'] ?? $sale->color),
                'size' => strtoupper(trim((string) ($datos['size'] ?? $sale->size ?? NormalizadorStockTallas::defaultSizeKey()))),
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
            $customerName = $datos['customer_data']['nombre_completo']
                ?? $datos['customer_data']['nombre']
                ?? $datos['customer_data']['name']
                ?? null;
            if ($customerName !== null && trim($customerName) !== '' && $customer->name !== $customerName) {
                $customer->update(['name' => trim($customerName)]);
            }

            $customer->asignarPedidoActivo($sale);

            return $sale->fresh(['product', 'productVariant']);
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
