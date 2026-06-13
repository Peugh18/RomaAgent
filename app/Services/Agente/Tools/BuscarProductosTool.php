<?php

namespace App\Services\Agente\Tools;

use App\Models\Product;
use App\Support\NormalizadorStockTallas;

class BuscarProductosTool
{
    /** @return array{name:string,description:string,parameters:array<string,mixed>} */
    public static function definition(): array
    {
        return [
            'name' => 'buscar_productos',
            'description' => 'Busca productos disponibles por nombre/tags y filtros opcionales (color, talla, precio, foto). Devuelve variantes con stock real.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'q' => ['type' => 'string', 'description' => 'Consulta de búsqueda (nombre/tags)'],
                    'color' => ['type' => 'string'],
                    'size' => ['type' => 'string', 'description' => 'Si no se envía, se usa la talla estándar'],
                    'min_price' => ['type' => 'number'],
                    'max_price' => ['type' => 'number'],
                    'has_photo' => ['type' => 'boolean'],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public static function execute(array $args): array
    {
        $q = trim((string) ($args['q'] ?? ''));

        // Ignorar palabras genéricas que harían que la búsqueda estricta falle
        $palabrasGenericas = ['vestido', 'vestidos', 'ropa', 'prenda', 'modelo', 'modelos', 'quiero', 'buscar', 'otro', 'otros', 'tienes', 'hay', 'algún', 'algun'];
        $pattern = '/\b('.implode('|', $palabrasGenericas).')\b/iu';
        $qLimpio = trim((string) preg_replace($pattern, '', $q));

        // Limpiar espacios dobles que hayan quedado
        $q = trim((string) preg_replace('/\s+/', ' ', $qLimpio));

        $color = trim((string) ($args['color'] ?? ''));
        $size = strtoupper(trim((string) ($args['size'] ?? NormalizadorStockTallas::defaultSizeKey())));
        $min = is_numeric($args['min_price'] ?? null) ? (float) $args['min_price'] : null;
        $max = is_numeric($args['max_price'] ?? null) ? (float) $args['max_price'] : null;
        $hasPhoto = (bool) ($args['has_photo'] ?? false);
        $limit = 5; // Forzar límite a 5 para dar variedad sin consumir muchos tokens

        $query = Product::query()
            ->where('status', Product::ESTADO_DISPONIBLE)
            ->with(['category', 'variants']);

        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function ($qq) use ($like) {
                $qq->where('name', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhere('tags_ia', 'like', $like);
            });
        }

        if ($min !== null) {
            $query->where('price', '>=', $min);
        }
        if ($max !== null) {
            $query->where('price', '<=', $max);
        }

        if ($color !== '') {
            $query->whereHas('variants', function ($qVariant) use ($color) {
                $qVariant->where('color', 'like', '%'.$color.'%');
            });
        }

        $productos = $query->inRandomOrder()->limit(min(50, max(1, $limit)))->get();

        $items = [];
        foreach ($productos as $p) {
            $variants = $p->variants;
            if ($color !== '') {
                $variants = $variants->filter(fn ($v) => stripos((string) $v->color, $color) !== false);
            }
            if ($hasPhoto) {
                $variants = $variants->filter(fn ($v) => ! empty($v->image_url) || ! empty($v->image_path));
            }

            if ($variants->isEmpty()) {
                continue;
            }

            $variantsArr = [];
            $hasStock = false;
            foreach ($variants as $v) {
                $stock = NormalizadorStockTallas::normalize($v->sizes_stock ?? []);
                $qty = (int) ($stock[$size] ?? 0);
                $hasStock = $hasStock || $qty > 0;
                $variantsArr[] = [
                    'id' => $v->id,
                    'color' => $v->color,
                    'image_url' => $v->image_url ?: $v->image_path,
                    'stock' => $stock,
                    'stock_estandar' => $qty,
                    'talla' => NormalizadorStockTallas::etiquetaPublica($size),
                ];
            }

            $items[] = [
                'id' => $p->id,
                'name' => $p->name,
                'category' => $p->category?->name,
                'description' => $p->description,
                'tags_ia' => $p->tags_ia ?? [],
                'precios' => [
                    'normal' => (float) $p->price,
                    'tiktok' => $p->price_tiktok !== null ? (float) $p->price_tiktok : null,
                    'promo_activa' => (bool) $p->discount_active,
                    'promo_descuento' => $p->discount !== null ? (float) $p->discount : null,
                    'normal_con_promo' => $p->precioNormalConPromo(),
                ],
                'tiene_stock' => $hasStock,
                'variants' => array_values($variantsArr),
            ];
        }

        return [
            'ok' => true,
            'count' => count($items),
            'items' => $items,
        ];
    }
}
