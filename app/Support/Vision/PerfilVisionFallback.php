<?php

namespace App\Support\Vision;

use App\Models\Product;
use App\Models\ProductVariant;

/**
 * Perfiles de visión mínimos sin llamadas a Gemini.
 * Garantizan matching textual por nombre de producto y color de variante.
 */
class PerfilVisionFallback
{
    /** @var array<string, list<string>> */
    private const ALIAS_COLORES = [
        'lila' => ['lila', 'violeta', 'morado', 'lavanda', 'purpura', 'púrpura'],
        'violeta' => ['violeta', 'lila', 'morado', 'lavanda'],
        'morado' => ['morado', 'lila', 'violeta', 'purpura', 'púrpura'],
        'camel' => ['camel', 'beige', 'crema', 'arena', 'café claro', 'cafe claro', 'tono camel'],
        'beige' => ['beige', 'camel', 'crema', 'arena'],
        'azul' => ['azul', 'celeste', 'marino', 'índigo', 'indigo', 'turquesa'],
        'celeste' => ['celeste', 'azul claro', 'cielo'],
        'rojo' => ['rojo', 'roja', 'carmesí', 'carmesi', 'burdeos'],
        'naranja' => ['naranja', 'anaranjado', 'coral'],
        'negro' => ['negro', 'negra'],
        'blanco' => ['blanco', 'blanca', 'off white'],
        'verde' => ['verde', 'oliva', 'esmeralda'],
        'rosa' => ['rosa', 'rosado', 'rosada', 'fucsia'],
        'gris' => ['gris', 'plomo', 'plateado'],
        'dorado' => ['dorado', 'dorada', 'gold', 'oro'],
    ];

    /**
     * @return array<string, mixed>
     */
    public static function construirPerfilProducto(Product $product): array
    {
        $keywords = array_values(array_unique(array_merge(
            self::normalizarLista($product->name),
            self::normalizarLista($product->tags_ia ?? []),
            self::normalizarLista($product->description ?? ''),
        )));

        return [
            'tipo_prenda' => 'otro',
            'material_aparente' => '',
            'keywords' => $keywords,
            'origen' => 'fallback',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function construirPerfilColor(ProductVariant $variant): array
    {
        $color = mb_strtolower(trim($variant->color));
        $aliases = self::aliasesParaColor($color);

        return [
            'color_canonical' => $variant->color,
            'colores_dominantes' => array_values(array_unique(array_merge([$color], $aliases))),
            'aliases' => $aliases,
            'tono' => 'medio',
            'origen' => 'fallback',
        ];
    }

    public static function aplicarProducto(Product $product): void
    {
        $product->update([
            'vision_profile' => self::construirPerfilProducto($product),
            'vision_profile_at' => now(),
        ]);
    }

    public static function aplicarColor(ProductVariant $variant): void
    {
        $variant->update([
            'color_profile' => self::construirPerfilColor($variant),
            'color_profile_at' => now(),
        ]);
    }

    /**
     * @return list<string>
     */
    public static function aliasesParaColor(string $color): array
    {
        $key = mb_strtolower(trim($color));
        if ($key === '') {
            return [];
        }

        $aliases = self::ALIAS_COLORES[$key] ?? [$key];

        return array_values(array_unique($aliases));
    }

    /**
     * @return list<string>
     */
    private static function normalizarLista(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/[\s,;|]+/u', mb_strtolower(trim($value))) ?: [];
        }

        if (! is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $item) {
            if (is_string($item) && trim($item) !== '') {
                $out[] = mb_strtolower(trim($item));
            }
        }

        return array_values(array_unique($out));
    }
}
