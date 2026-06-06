<?php

namespace App\Services;

use App\Models\ProductVariant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ServicioMediaProducto
{
    public function resolvePublicUrl(?ProductVariant $variant): ?string
    {
        if (! $variant) {
            return null;
        }

        if (! empty($variant->image_path)) {
            return Storage::disk('public')->url($variant->image_path);
        }

        if (! empty($variant->image_url)) {
            return $variant->image_url;
        }

        return null;
    }

    /**
     * URL absoluta accesible por WhatsApp/Meta (usa PUBLIC_APP_URL).
     */
    public function resolveAbsolutePublicUrl(?ProductVariant $variant): ?string
    {
        $url = $this->resolvePublicUrl($variant);

        if ($url === null || $url === '') {
            return null;
        }

        $publicBase = rtrim((string) config('app.public_url', config('app.url')), '/');
        $appBase = rtrim((string) config('app.url'), '/');

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            if ($appBase !== '' && $publicBase !== $appBase && str_starts_with($url, $appBase)) {
                return $publicBase.substr($url, strlen($appBase));
            }

            return $url;
        }

        return $publicBase.'/'.ltrim($url, '/');
    }

    /**
     * Verifica que la imagen exista localmente o responda por HTTP.
     * No usa round-trip por ngrok (falla/timeout desde el mismo servidor).
     */
    public function urlEsAccesibleParaWhatsapp(string $url): bool
    {
        $rutaLocal = $this->rutaLocalDesdeUrlPublica($url);

        if ($rutaLocal !== null && Storage::disk('public')->exists($rutaLocal)) {
            return $this->archivoEsImagenEnDisco(Storage::disk('public')->path($rutaLocal));
        }

        $urlLocal = $this->urlServidorLocal($rutaLocal);

        if ($urlLocal !== null && ($this->probarAccesoHttp($urlLocal, 'HEAD') || $this->probarAccesoHttp($urlLocal, 'GET'))) {
            return true;
        }

        return $this->probarAccesoHttp($url, 'HEAD') || $this->probarAccesoHttp($url, 'GET');
    }

    public function rutaLocalDesdeUrlPublica(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path) || ! str_contains($path, '/storage/')) {
            return null;
        }

        $relative = ltrim((string) preg_replace('#^.*/storage/#', '', $path), '/');

        return $relative !== '' ? $relative : null;
    }

    private function urlServidorLocal(?string $rutaRelativa): ?string
    {
        if ($rutaRelativa === null || $rutaRelativa === '') {
            return null;
        }

        $port = (string) (config('app.port') ?: '8000');

        return 'http://127.0.0.1:'.$port.'/storage/'.$rutaRelativa;
    }

    private function archivoEsImagenEnDisco(string $absolutePath): bool
    {
        if (! is_file($absolutePath)) {
            return false;
        }

        $mime = mime_content_type($absolutePath);

        if (is_string($mime) && str_starts_with($mime, 'image/')) {
            return true;
        }

        $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));

        return in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
    }

    private function probarAccesoHttp(string $url, string $method): bool
    {
        try {
            $request = Http::timeout(3)
                ->connectTimeout(2)
                ->withHeaders(['ngrok-skip-browser-warning' => '1']);

            $response = $method === 'GET'
                ? $request->get($url)
                : $request->head($url);

            if (! $response->successful()) {
                return false;
            }

            $contentType = strtolower((string) $response->header('Content-Type'));

            return str_starts_with($contentType, 'image/');
        } catch (\Throwable) {
            return false;
        }
    }

    public function storeVariantPhoto(ProductVariant $variant, UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension() ?: 'jpg';
        $colorSlug = Str::slug($variant->color) ?: 'color';
        $path = "products/{$variant->product_id}/{$colorSlug}-".uniqid().".{$extension}";

        Storage::disk('public')->putFileAs(
            dirname($path),
            $file,
            basename($path)
        );

        if ($variant->image_path) {
            Storage::disk('public')->delete($variant->image_path);
        }

        $variant->update([
            'image_path' => $path,
            'image_url' => null,
        ]);

        return $path;
    }
}
