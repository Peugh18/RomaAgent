<?php

namespace App\Services\Agente\Tools;

use App\Models\Product;
use App\Support\NormalizadorStockTallas;

class VerificarStockTool
{
    /** @return array{name:string,description:string,parameters:array<string,mixed>} */
    public static function definition(): array
    {
        return [
            'name' => 'verificar_stock',
            'description' => 'Verifica stock disponible de un producto/variante y talla. Acepta product_id o product_name.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'product_id' => ['type' => 'integer'],
                    'product_name' => ['type' => 'string'],
                    'variant_id' => ['type' => 'integer'],
                    'color' => ['type' => 'string'],
                    'size' => ['type' => 'string'],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    /**
     * Mapea input de talla (puede ser "estándar", "talla estándar", etc.) a la clave interna de BD.
     */
    private static function mapearTallaInput(?string $size): string
    {
        $raw = strtolower(trim((string) $size));

        // Si la IA dice "estándar", "estandar", "talla estándar", etc. → mapear a clave interna
        if (in_array($raw, ['estándar', 'estandar', 'standar', 'estandar', 'talla estándar', 'talla estandar', 'talla standar'], true)) {
            return NormalizadorStockTallas::defaultSizeKey();
        }

        // Si está vacío, usar default
        if ($raw === '') {
            return NormalizadorStockTallas::defaultSizeKey();
        }

        // De lo contrario, normalizar a mayúsculas
        return strtoupper(trim((string) $size));
    }

    public static function execute(array $args): array
    {
        $size = self::mapearTallaInput($args['size'] ?? null);

        $product = null;
        if (! empty($args['product_id'])) {
            $product = Product::with('variants')->find((int) $args['product_id']);
        }
        if ($product === null) {
            $name = trim((string) ($args['product_name'] ?? ''));
            if ($name !== '') {
                $product = Product::with('variants')->where('name', $name)->first();
            }
        }
        if ($product === null) {
            return ['ok' => false, 'error' => 'Producto no encontrado'];
        }

        $variant = null;
        if (! empty($args['variant_id'])) {
            $variant = $product->variants->firstWhere('id', (int) $args['variant_id']);
        }
        if ($variant === null) {
            $color = trim((string) ($args['color'] ?? ''));
            if ($color !== '') {
                $variant = $product->variants->first(fn ($v) => strcasecmp((string) $v->color, $color) === 0);
            }
        }

        if ($variant !== null) {
            $stock = NormalizadorStockTallas::normalize($variant->sizes_stock ?? []);
            $qty = (int) ($stock[$size] ?? 0);
            if ($qty > 0) {
                return [
                    'ok' => true,
                    'available' => true,
                    'qty' => $qty,
                    'variant_id' => $variant->id,
                    'color' => $variant->color,
                    'size' => NormalizadorStockTallas::etiquetaPublica($size),
                ];
            }
        }

        foreach ($product->variants as $v) {
            $stock = NormalizadorStockTallas::normalize($v->sizes_stock ?? []);
            $qty = (int) ($stock[$size] ?? 0);
            if ($qty > 0) {
                return [
                    'ok' => true,
                    'available' => false,
                    'qty' => 0,
                    'suggestion' => [
                        'variant_id' => $v->id,
                        'color' => $v->color,
                        'qty' => $qty,
                    ],
                ];
            }
        }

        return ['ok' => true, 'available' => false, 'qty' => 0];
    }
}
