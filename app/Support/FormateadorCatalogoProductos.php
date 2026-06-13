<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;

class FormateadorCatalogoProductos
{
    private string $tallaEstandar;

    public function __construct(
        private string $simboloMoneda = 'S/',
        ?string $tallaEstandar = null,
    ) {
        $this->tallaEstandar = strtoupper(trim($tallaEstandar ?? NormalizadorStockTallas::defaultSizeKey()));
    }

    public static function simboloDesdeMoneda(string $moneda): string
    {
        return match ($moneda) {
            'USD' => '$',
            'EUR' => '€',
            default => 'S/',
        };
    }

    public function tallaEstandarConfigurada(): string
    {
        return $this->tallaEstandar;
    }

    /**
     * @param  Collection<int, Product>|iterable<Product>  $productos
     */
    public function formatearCatalogo(iterable $productos): string
    {
        $lista = $productos instanceof Collection ? $productos : collect($productos);

        if ($lista->isEmpty()) {
            return "# CATÁLOGO\nNo hay productos disponibles en este momento.";
        }

        $lineas = [
            '# CATÁLOGO DE PRODUCTOS DISPONIBLES',
            NormalizadorStockTallas::instruccionTallaParaCatalogo(),
            'Precio normal para venta directa; precio TikTok cuando el cliente viene de TikTok. El descuento promo solo aplica al precio normal.',
            '',
        ];

        foreach ($lista as $producto) {
            $lineas[] = $this->formatearProducto($producto);
            $lineas[] = '';
        }

        return rtrim(implode("\n", $lineas));
    }

    public function formatearProducto(Product $producto): string
    {
        $categoria = $producto->category?->name ?? 'Sin cat';
        $lineaNombre = sprintf('- [%s] **%s**', $categoria, $producto->name);

        $tags = $producto->tags_ia ?? [];
        if ($tags !== []) {
            $lineaNombre .= ' (Tags: '.implode(', ', $tags).')';
        }
        $lineas = [$lineaNombre];

        $precios = [sprintf('%s %s', $this->simboloMoneda, number_format((float) $producto->price, 2))];

        if ($producto->descuentoPromoActivo()) {
            $precios[] = sprintf('Promo: %s %s', $this->simboloMoneda, number_format((float) $producto->precioNormalConPromo(), 2));
        }

        if ($producto->price_tiktok !== null && (float) $producto->price_tiktok > 0) {
            $precios[] = sprintf('TikTok: %s %s', $this->simboloMoneda, number_format((float) $producto->price_tiktok, 2));
        }

        $lineas[] = '  Precio: '.implode(' | ', $precios);

        if (! empty($producto->description)) {
            $lineas[] = '  Desc: '.trim((string) $producto->description);
        }

        $variantes = $producto->relationLoaded('variants')
            ? $producto->variants
            : $producto->variants()->get();

        if ($variantes->isEmpty()) {
            $lineas[] = '  Stock: sin variantes';
        } else {
            $partesStock = [];
            foreach ($variantes as $variante) {
                $foto = $this->varianteTieneFoto($variante) ? 'foto' : 'no-foto';
                $stockStr = $this->formatearStockVarianteCompacto($variante);
                $partesStock[] = sprintf('%s (%s, %s)', $variante->color, $stockStr, $foto);
            }
            $lineas[] = '  Stock: '.implode('; ', $partesStock);
        }

        return implode("\n", $lineas);
    }

    private function formatearStockVarianteCompacto(ProductVariant $variante): string
    {
        $sizesStock = NormalizadorStockTallas::normalize($variante->sizes_stock ?? []);

        if ($sizesStock === []) {
            return '0';
        }

        $partes = [];
        foreach ($sizesStock as $talla => $cantidad) {
            $tallaLabel = strtoupper(trim((string) $talla));
            $etiqueta = NormalizadorStockTallas::etiquetaPublica($tallaLabel);
            $partes[] = "{$etiqueta}:".max(0, (int) $cantidad);
        }

        return implode(', ', $partes);
    }

    private function varianteTieneFoto(ProductVariant $variante): bool
    {
        return ! empty($variante->image_path) || ! empty($variante->image_url);
    }
}
