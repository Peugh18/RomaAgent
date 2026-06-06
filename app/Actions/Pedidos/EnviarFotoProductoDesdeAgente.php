<?php

namespace App\Actions\Pedidos;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ServicioMediaProducto;
use Illuminate\Support\Facades\Log;

class EnviarFotoProductoDesdeAgente
{
    public function __construct(
        private ServicioMediaProducto $mediaProducto,
    ) {}

    /**
     * @return array{ok: bool, image_url: string|null, product_name: string|null, color: string|null, error: string|null}
     */
    public function handle(string $productName, string $color): array
    {
        $productName = trim($productName);
        $color = trim($color);

        Log::info('EnviarFotoProductoDesdeAgente: buscando', [
            'product_name' => $productName,
            'color' => $color,
        ]);

        $product = $this->resolverProducto($productName);

        if ($product === null) {
            Log::warning('EnviarFotoProductoDesdeAgente: producto no encontrado', [
                'product_name' => $productName,
            ]);

            return [
                'ok' => false,
                'image_url' => null,
                'product_name' => null,
                'color' => null,
                'error' => 'Producto no encontrado o no disponible.',
            ];
        }

        $needle = mb_strtolower($color);
        /** @var ProductVariant|null $variant */
        $variant = $product->variants->first(
            fn (ProductVariant $v): bool => mb_strtolower($v->color) === $needle
                || str_contains(mb_strtolower($v->color), $needle)
                || str_contains($needle, mb_strtolower($v->color))
        );

        if ($variant === null) {
            return [
                'ok' => false,
                'image_url' => null,
                'product_name' => $product->name,
                'color' => $color,
                'error' => 'Color no encontrado para este producto.',
            ];
        }

        $url = $this->mediaProducto->resolveAbsolutePublicUrl($variant);

        if ($url === null) {
            return [
                'ok' => false,
                'image_url' => null,
                'product_name' => $product->name,
                'color' => $variant->color,
                'error' => 'Este color no tiene foto cargada en Productos.',
            ];
        }

        if (str_contains($url, 'localhost') || str_contains($url, '127.0.0.1')) {
            Log::error('EnviarFotoProductoDesdeAgente: URL no pública para WhatsApp', [
                'image_url' => $url,
                'hint' => 'Configura PUBLIC_APP_URL en .env con tu ngrok/dominio público',
            ]);

            return [
                'ok' => false,
                'image_url' => null,
                'product_name' => $product->name,
                'color' => $variant->color,
                'error' => 'La foto no es accesible públicamente. Configura PUBLIC_APP_URL en el servidor.',
            ];
        }

        if (! $this->mediaProducto->urlEsAccesibleParaWhatsapp($url)) {
            Log::error('EnviarFotoProductoDesdeAgente: URL de foto no responde', [
                'image_url' => $url,
                'public_app_url' => config('app.public_url'),
                'app_url' => config('app.url'),
            ]);

            return [
                'ok' => false,
                'image_url' => null,
                'product_name' => $product->name,
                'color' => $variant->color,
                'error' => 'La URL de la foto no responde. PUBLIC_APP_URL debe ser el mismo ngrok activo que APP_URL.',
            ];
        }

        Log::info('EnviarFotoProductoDesdeAgente: foto encontrada', [
            'product_name' => $product->name,
            'color' => $variant->color,
            'image_url' => $url,
        ]);

        return [
            'ok' => true,
            'image_url' => $url,
            'product_name' => $product->name,
            'color' => $variant->color,
            'error' => null,
        ];
    }

    private function resolverProducto(string $productName): ?Product
    {
        $nombre = mb_strtolower($productName);

        $exacto = Product::query()
            ->where('status', Product::ESTADO_DISPONIBLE)
            ->whereRaw('LOWER(name) = ?', [$nombre])
            ->with('variants')
            ->first();

        if ($exacto !== null) {
            return $exacto;
        }

        return Product::query()
            ->where('status', Product::ESTADO_DISPONIBLE)
            ->where('name', 'like', '%'.$productName.'%')
            ->with('variants')
            ->first();
    }
}
