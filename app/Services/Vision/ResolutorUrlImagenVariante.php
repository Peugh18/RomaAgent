<?php

namespace App\Services\Vision;

use App\Models\ProductVariant;
use Illuminate\Support\Facades\Storage;

class ResolutorUrlImagenVariante
{
    public function resolver(ProductVariant $variant): ?string
    {
        $variant->loadMissing('product');

        if (! empty($variant->image_url)) {
            return $variant->image_url;
        }

        if (! empty($variant->image_path)) {
            if (str_starts_with($variant->image_path, 'http')) {
                return $variant->image_path;
            }

            return Storage::disk('public')->url($variant->image_path);
        }

        if (! empty($variant->image)) {
            return $variant->image;
        }

        $product = $variant->product;
        if ($product !== null) {
            if (! empty($product->image_url)) {
                return $product->image_url;
            }

            if (! empty($product->image_path)) {
                if (str_starts_with($product->image_path, 'http')) {
                    return $product->image_path;
                }

                return Storage::disk('public')->url($product->image_path);
            }

            if (! empty($product->image)) {
                return $product->image;
            }
        }

        $storagePaths = [
            "products/{$variant->product_id}/{$variant->id}.jpg",
            "products/{$variant->product_id}/{$variant->id}.png",
            "products/{$variant->product_id}/{$variant->color}.jpg",
            "products/{$variant->product_id}/{$variant->color}.png",
            "products/{$variant->product_id}/main.jpg",
            "products/{$variant->product_id}/main.png",
        ];

        foreach ($storagePaths as $path) {
            if (Storage::disk('public')->exists($path)) {
                return Storage::disk('public')->url($path);
            }
        }

        $publicBase = rtrim((string) config('app.public_url', config('app.url')), '/');
        $possibleUrls = [
            "{$publicBase}/storage/products/{$variant->product_id}/{$variant->id}.jpg",
            "{$publicBase}/storage/products/{$variant->product_id}/{$variant->color}.jpg",
        ];

        foreach ($possibleUrls as $url) {
            if ($this->urlResponde($url)) {
                return $url;
            }
        }

        return null;
    }

    private function urlResponde(string $url): bool
    {
        try {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            return $httpCode === 200;
        } catch (\Throwable) {
            return false;
        }
    }
}
