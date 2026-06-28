<?php

namespace App\Services\Vision;

use App\Exceptions\GeminiQuotaExceededException;
use App\Models\ProductVariant;

class GeneradorPerfilVisionCatalogo
{
    public function __construct(
        private GarmentVisionService $visionService,
    ) {}

    /**
     * Analiza la imagen de una variante usando el motor unificado de visión.
     * Devuelve el perfil estructurado en el formato esperado por la BD.
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

        return [
            'producto' => $raw,
            'color' => [
                'color_canonical' => $analysis->colorPrincipal ?? $variant->color,
                'colores_dominantes' => $analysis->colorPrincipal ? [$analysis->colorPrincipal] : [],
                'origen' => 'gemini',
            ],
        ];
    }
}
