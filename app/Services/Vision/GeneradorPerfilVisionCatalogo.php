<?php

namespace App\Services\Vision;

use App\Exceptions\GeminiQuotaExceededException;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Vision\ParseadorRespuestaJsonGemini;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeneradorPerfilVisionCatalogo
{
    public function __construct(
        private GarmentVisionService $visionService,
    ) {}

    /**
     * Una sola llamada Gemini: perfil de producto + color (ahorra cuota vs 2 llamadas).
     *
     * @return array{producto: array<string, mixed>, color: array<string, mixed>}|null
     *
     * @throws GeminiQuotaExceededException
     */
    public function generarPerfilesVariante(ProductVariant $variant, string $imageUrl): ?array
    {
        $analysis = $this->visionService->analyze($imageUrl);
        if ($analysis === null) {
            return null;
        }

        $raw = $analysis->rawJson;
        $raw['origen'] = 'gemini';

        // Convertimos el análisis estructurado en el formato esperado por la BD
        return [
            'producto' => $raw,
            'color' => [
                'color_canonical' => $analysis->colorPrincipal ?? $variant->color,
                'colores_dominantes' => [$analysis->colorPrincipal],
                'origen' => 'gemini',
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function generarPerfilProducto(Product $product, string $imageUrl): ?array
    {
        $media = $this->cargador->desdeUrl($imageUrl);
        if ($media === null) {
            return null;
        }

        $prompt = <<<PROMPT
Analiza la foto de catálogo de un producto de moda. Responde SOLO JSON válido (sin markdown).
El producto se llama "{$product->name}".
Describe el PRODUCTO/MODELO (no el color de fondo ni la modelo). Ignora marcas de agua o UI de redes sociales.

Esquema:
{
  "tipo_prenda": "vestido|blusa|pantalón|accesorio|otro",
  "material_aparente": "texto libre",
  "silueta": "corta|midi|larga|otro",
  "patron": "liso|estampado|rayas|otro",
  "detalles": ["detalle visible", "..."],
  "keywords": ["palabras clave para búsqueda", "..."]
}
PROMPT;

        return $this->analizarConPrompt($media, $prompt);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function generarPerfilColor(ProductVariant $variant, string $imageUrl): ?array
    {
        $media = $this->cargador->desdeUrl($imageUrl);
        if ($media === null) {
            return null;
        }

        $color = $variant->color;
        $productName = $variant->product?->name ?? 'producto';

        $prompt = <<<PROMPT
Analiza el COLOR dominante de esta variante de "{$productName}" (color registrado: "{$color}").
Responde SOLO JSON válido (sin markdown).

Esquema:
{
  "color_canonical": "{$color}",
  "colores_dominantes": ["nombre color 1", "nombre color 2"],
  "aliases": ["sinónimos en español del color"],
  "tono": "claro|medio|oscuro"
}
PROMPT;

        return $this->analizarConPrompt($media, $prompt);
    }

    /**
     * @param  array{bytes: string, mime: string}  $media
     * @return array<string, mixed>|null
     *
     * @throws GeminiQuotaExceededException
     */
    private function analizarConPrompt(array $media, string $prompt): ?array
    {
        $apiKey = $this->obtenerApiKey();
        if ($apiKey === null) {
            return null;
        }

        $modelo = $this->obtenerModelo();
        $endpoint = $this->construirEndpoint($modelo);
        $mime = $this->normalizarMimeImagen($media['mime']);

        $payload = [
            'contents' => [[
                'parts' => [
                    ['text' => $prompt],
                    [
                        'inline_data' => [
                            'mime_type' => $mime,
                            'data' => base64_encode($media['bytes']),
                        ],
                    ],
                ],
            ]],
            'generationConfig' => [
                'temperature' => 0.1,
                'maxOutputTokens' => 1024,
                'responseMimeType' => 'application/json',
            ],
        ];

        return $this->ejecutarConRetry(function () use ($endpoint, $payload, $apiKey) {
            $response = Http::withHeaders($this->headersGemini($apiKey))
                ->timeout($this->timeout)
                ->post($endpoint, $payload);

            $data = $this->procesarRespuestaApi($response);
            $text = $this->extraerTextoRespuesta($data);

            return ParseadorRespuestaJsonGemini::parse($text);
        });
    }

    public function aplicarPerfilProducto(Product $product, string $imageUrl): bool
    {
        $profile = $this->generarPerfilProducto($product, $imageUrl);
        if ($profile === null) {
            Log::warning('GeneradorPerfilVisionCatalogo: perfil producto vacío', [
                'product_id' => $product->id,
            ]);

            return false;
        }

        $product->update([
            'vision_profile' => $profile,
            'vision_profile_at' => now(),
        ]);

        return true;
    }

    public function aplicarPerfilColor(ProductVariant $variant, string $imageUrl): bool
    {
        $profile = $this->generarPerfilColor($variant, $imageUrl);
        if ($profile === null) {
            Log::warning('GeneradorPerfilVisionCatalogo: perfil color vacío', [
                'variant_id' => $variant->id,
            ]);

            return false;
        }

        $variant->update([
            'color_profile' => $profile,
            'color_profile_at' => now(),
        ]);

        return true;
    }
}
