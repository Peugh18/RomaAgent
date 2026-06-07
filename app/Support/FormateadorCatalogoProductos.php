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
            'Toda la tienda usa talla estándar. Al hablar con la clienta di "talla estándar", nunca "única" ni "UNICA".',
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
        $categoria = $producto->category?->name ?? 'Sin categoría';
        $precioNormal = number_format((float) $producto->price, 2);
        $lineas = [
            sprintf('- **%s** | Categoría: %s', $producto->name, $categoria),
            sprintf('  Precio normal: %s %s', $this->simboloMoneda, $precioNormal),
        ];

        if ($producto->descuentoPromoActivo()) {
            $lineas[] = sprintf(
                '  Precio normal con promo: %s %s (descuento %s %s — solo venta directa, no aplica a TikTok)',
                $this->simboloMoneda,
                number_format((float) $producto->precioNormalConPromo(), 2),
                $this->simboloMoneda,
                number_format((float) $producto->discount, 2),
            );
        }

        if ($producto->price_tiktok !== null && (float) $producto->price_tiktok > 0) {
            $lineas[] = sprintf(
                '  Precio TikTok: %s %s',
                $this->simboloMoneda,
                number_format((float) $producto->price_tiktok, 2)
            );
        }

        if (! empty($producto->description)) {
            $lineas[] = '  Descripción: '.trim((string) $producto->description);
        }

        $tags = $producto->tags_ia ?? [];
        if ($tags !== []) {
            $lineas[] = '  Tags: '.implode(', ', $tags);
        }

        $variantes = $producto->relationLoaded('variants')
            ? $producto->variants
            : $producto->variants()->get();

        if ($variantes->isEmpty()) {
            $lineas[] = '  Colores y stock: sin variantes registradas';
        } else {
            $lineas[] = '  Colores y stock:';
            foreach ($variantes as $variante) {
                $foto = $this->varianteTieneFoto($variante) ? 'foto: sí' : 'foto: no';
                $lineas[] = '    · '.$variante->color.': '.$this->formatearStockVariante($variante)." ({$foto})";
            }
        }

        $stockTotal = $this->stockTotalProducto($variantes);
        $lineas[] = sprintf('  Stock total: %d unidad(es)', $stockTotal);

        return implode("\n", $lineas);
    }

    /**
     * @param  Collection<int, ProductVariant>|iterable<ProductVariant>  $variantes
     */
    private function stockTotalProducto(iterable $variantes): int
    {
        $total = 0;

        foreach ($variantes as $variante) {
            foreach ($variante->sizes_stock ?? [] as $qty) {
                $total += max(0, (int) $qty);
            }
        }

        return $total;
    }

    private function formatearStockVariante(ProductVariant $variante): string
    {
        $sizesStock = NormalizadorStockTallas::normalize($variante->sizes_stock ?? []);

        if ($sizesStock === []) {
            return 'sin stock registrado';
        }

        $partes = $this->ordenarTallasDesdeBd($sizesStock);

        return implode(', ', $partes);
    }

    /**
     * @param  array<string, int>  $sizesStock
     * @return list<string>
     */
    private function ordenarTallasDesdeBd(array $sizesStock): array
    {
        $estandar = [];
        $extras = [];

        foreach ($sizesStock as $talla => $cantidad) {
            $tallaLabel = strtoupper(trim((string) $talla));
            $linea = $this->formatearLineaTallaBd($tallaLabel, (int) $cantidad);

            if ($tallaLabel === $this->tallaEstandar) {
                $estandar[] = $linea;
            } else {
                $extras[$tallaLabel] = $linea;
            }
        }

        ksort($extras);

        return array_values([...$estandar, ...$extras]);
    }

    private function formatearLineaTallaBd(string $talla, int $cantidad): string
    {
        $etiqueta = NormalizadorStockTallas::etiquetaPublica($talla);

        if ($cantidad > 0) {
            return sprintf('%s: %d en stock', $etiqueta, $cantidad);
        }

        return sprintf('%s: agotado', $etiqueta);
    }

    private function varianteTieneFoto(ProductVariant $variante): bool
    {
        return ! empty($variante->image_path) || ! empty($variante->image_url);
    }
}
