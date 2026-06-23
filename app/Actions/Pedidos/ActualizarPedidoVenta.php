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

            if ($product !== null && $product->name !== 'Pedido') {
                $colorToValidate = $datos['color'] ?? $sale->color;
                $sizeToValidate = $datos['size'] ?? $sale->size ?? NormalizadorStockTallas::defaultSizeKey();
                $qtyToValidate = max(1, (int) ($datos['quantity'] ?? $sale->quantity ?? 1));

                $this->validarVarianteYStock($product, $colorToValidate, $sizeToValidate, $qtyToValidate);
            }

            $variant = $this->resolverVariante($product, $datos['color'] ?? $sale->color);

            $unitPrice = ValidadorPrecioPedido::resolverPrecioUnitario(
                $product,
                $datos['unit_price'] ?? null,
            );

            $quantity = max(1, (int) ($datos['quantity'] ?? $sale->quantity));

            $newStatus = isset($datos['status']) ? SaleStatus::from($datos['status']) : null;

            // Validación backend para impedir datos_listos incompletos
            if ($newStatus === SaleStatus::DatosListos) {
                $customerData = array_merge($sale->customer_data ?? [], $datos['customer_data'] ?? []);
                $tipoEnvio = strtolower($customerData['tipo_envio'] ?? '');
                $nombre = trim($customerData['nombre'] ?? $customerData['name'] ?? $customerData['nombre_completo'] ?? $customer->name ?? '');

                if ($nombre === '') {
                    throw new \InvalidArgumentException('Para confirmar los datos listos es obligatorio registrar el nombre de la clienta en customer_data.');
                }

                if ($tipoEnvio === 'motorizado') {
                    if (empty($customerData['distrito']) || empty($customerData['direccion'])) {
                        throw new \InvalidArgumentException('Para envío motorizado es obligatorio registrar el distrito y la dirección en customer_data antes de pasar a datos_listos.');
                    }
                } elseif ($tipoEnvio === 'shalom') {
                    if (empty($customerData['dni']) || empty($customerData['agencia'])) {
                        throw new \InvalidArgumentException('Para envío por Shalom es obligatorio registrar el DNI y la agencia/provincia en customer_data antes de pasar a datos_listos.');
                    }
                } else {
                    throw new \InvalidArgumentException('Es obligatorio especificar un tipo de envío válido (motorizado o shalom) en customer_data antes de pasar a datos_listos.');
                }
            }

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
            $itemsToCalculate = (! empty($items) && is_array($items)) ? $items : ($sale->exists ? $sale->items->map(fn ($i) => ['product_name' => $i->product_name, 'quantity' => $i->quantity, 'unit_price' => $i->unit_price])->toArray() : []);

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
                $total = $itemsSubtotal;
            } else {
                $total = ValidadorPrecioPedido::calcularTotal($unitPrice, $quantity);
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
                'total_amount' => $total,
                'payment_method' => $datos['payment_method'] ?? $sale->payment_method,
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
                ?? $datos['customer_data']['nombre_completo']
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
                    $price = (float) ($itemData['unit_price'] ?? 0);

                    return ! ($name === 'Pedido' && $price === 0.0);
                });

                if (! empty($itemsReales) || empty($sale->items) || $sale->items->isEmpty()) {
                    foreach ($itemsReales as $itemData) {
                        $itemProduct = $this->resolverProducto($itemData['product_name'] ?? null);
                        if ($itemProduct !== null) {
                            $itemColor = $itemData['color'] ?? null;
                            $itemSize = $itemData['size'] ?? NormalizadorStockTallas::defaultSizeKey();
                            $itemQty = max(1, (int) ($itemData['quantity'] ?? 1));

                            $this->validarVarianteYStock($itemProduct, $itemColor, $itemSize, $itemQty);
                        }
                    }

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

            $cacheKey = "agente_memoria_visual_cliente_{$customer->id}";

            // Limpiar memoria visual en estados finales
            if (in_array($status, [SaleStatus::Confirmado, SaleStatus::Cancelado, SaleStatus::Entregado, SaleStatus::Enviado], true)) {
                \Illuminate\Support\Facades\Cache::forget($cacheKey);
            } else {
                // Limpiar memoria visual si uno de los items añadidos estaba en memoria
                if (isset($itemsReales) && !empty($itemsReales)) {
                    $memoria = \Illuminate\Support\Facades\Cache::get($cacheKey, []);
                    if (is_array($memoria) && !empty($memoria)) {
                        $memoriaIds = array_column($memoria, 'product_id');
                        $memoriaIds = array_filter($memoriaIds);
                        
                        foreach ($itemsReales as $itemData) {
                            $itemProduct = $this->resolverProducto($itemData['product_name'] ?? null);
                            if ($itemProduct && in_array($itemProduct->id, $memoriaIds, true)) {
                                \Illuminate\Support\Facades\Cache::forget($cacheKey);
                                break;
                            }
                        }
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

        $needle = mb_strtolower(trim(\Illuminate\Support\Str::ascii($color)), 'UTF-8');

        return $product->variants->first(function (ProductVariant $variant) use ($needle): bool {
            $variantColor = mb_strtolower(trim(\Illuminate\Support\Str::ascii($variant->color)), 'UTF-8');
            return $variantColor === $needle || str_contains($variantColor, $needle);
        });
    }

    private function validarVarianteYStock(?Product $product, ?string $color, ?string $size, int $quantity = 1): void
    {
        if ($product === null) {
            return;
        }

        // Si el producto no tiene variantes registradas, no podemos validar colores ni stock de forma específica
        if ($product->variants->isEmpty()) {
            return;
        }

        if ($color === null || trim($color) === '') {
            throw new \InvalidArgumentException(
                "Es obligatorio especificar un color para el producto '{$product->name}'."
            );
        }

        $variant = $this->resolverVariante($product, $color);
        if ($variant === null) {
            $coloresDisponibles = $product->variants->pluck('color')->unique()->implode(', ');
            throw new \InvalidArgumentException(
                "El color '{$color}' no está disponible para el producto '{$product->name}'. Colores disponibles: {$coloresDisponibles}."
            );
        }

        // Validar talla y stock de esa variante
        $sizesStock = NormalizadorStockTallas::normalize($variant->sizes_stock ?? []);

        if (empty($sizesStock)) {
            throw new \InvalidArgumentException(
                "El producto '{$product->name}' en color '{$variant->color}' se encuentra temporalmente agotado."
            );
        }

        // Normalizar la talla buscada
        $tallaBuscada = NormalizadorStockTallas::esTallaEstandar((string) $size)
            ? NormalizadorStockTallas::defaultSizeKey()
            : mb_strtoupper(trim((string) $size), 'UTF-8');

        // REGLA: Si la variante solo tiene UNA talla en stock y es la talla estándar interna (UNICA),
        // ignoramos lo que haya enviado Gemini y forzamos el uso de la talla estándar para evitar errores de validación.
        if (count($sizesStock) === 1 && array_key_exists(NormalizadorStockTallas::defaultSizeKey(), $sizesStock)) {
            $tallaBuscada = NormalizadorStockTallas::defaultSizeKey();
        }

        // Si no hay stock para la talla especificada o no existe la talla en la variante
        if (! array_key_exists($tallaBuscada, $sizesStock)) {
            $tallasDisponibles = collect(array_keys($sizesStock))
                ->map(fn ($t) => NormalizadorStockTallas::etiquetaPublica($t))
                ->implode(', ');

            throw new \InvalidArgumentException(
                "La talla '{$size}' no está disponible para '{$product->name}' en color '{$variant->color}'. Tallas disponibles: {$tallasDisponibles}."
            );
        }

        $stockDisponible = (int) $sizesStock[$tallaBuscada];
        if ($stockDisponible <= 0) {
            throw new \InvalidArgumentException(
                "El producto '{$product->name}' en color '{$variant->color}' y talla '{$size}' está agotado."
            );
        }

        if ($quantity > $stockDisponible) {
            throw new \InvalidArgumentException(
                "No hay suficiente stock para '{$product->name}' en color '{$variant->color}' y talla '{$size}'. Stock disponible: {$stockDisponible} unidades."
            );
        }
    }
}
