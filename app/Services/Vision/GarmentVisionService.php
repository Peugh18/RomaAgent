<?php

namespace App\Services\Vision;

use App\DTOs\GarmentAnalysisResult;
use App\Exceptions\GeminiQuotaExceededException;
use App\Services\ConfiguracionAgente;
use App\Services\Media\BaseGeminiService;
use App\Services\Media\CargadorBytesMedia;
use App\Support\Vision\ParseadorRespuestaJsonGemini;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GarmentVisionService extends BaseGeminiService
{
    public function __construct(
        ConfiguracionAgente $configuracion,
        private CargadorBytesMedia $cargador,
    ) {
        parent::__construct($configuracion);
    }

    /**
     * Analiza una imagen (vía URL) y devuelve un DTO estandarizado con el perfil visual y la descripción vectorial.
     *
     * @throws GeminiQuotaExceededException
     */
    public function analyze(string $imageUrl): ?GarmentAnalysisResult
    {
        $apiKey = $this->obtenerApiKey();
        if ($apiKey === null) {
            return null;
        }

        $media = $this->cargador->desdeUrl($imageUrl);
        if ($media === null) {
            Log::warning('GarmentVisionService: no se pudo cargar la imagen', ['url' => $imageUrl]);

            return null;
        }

        $modelo = $this->obtenerModelo();
        $endpoint = $this->construirEndpoint($modelo);
        $mime = $this->normalizarMimeImagen($media['mime']);

        $prompt = OptimizedVisionPrompts::promptUniversalPrenda();

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
                'temperature' => 0.1, // Baja temperatura para mayor precisión y determinismo
            ],
        ];

        $response = $this->ejecutarConRetry(function () use ($endpoint, $payload, $apiKey) {
            $response = Http::withHeaders($this->headersGemini($apiKey))
                ->timeout($this->timeout)
                ->post($endpoint, $payload);

            $data = $this->procesarRespuestaApi($response);
            if ($data === null) {
                return null;
            }

            // Extraer texto — en modelos con pensamiento pueden llegar múltiples parts.
            // Concatenamos todos los text parts para no perder el JSON.
            $text = null;
            if (isset($data['candidates'][0]['content']['parts'])) {
                $textos = [];
                foreach ($data['candidates'][0]['content']['parts'] as $part) {
                    if (isset($part['text']) && trim($part['text']) !== '') {
                        $textos[] = trim($part['text']);
                    }
                }
                if (! empty($textos)) {
                    $text = implode("\n", $textos);
                }
            }

            if ($text === null) {
                return null;
            }

            return ParseadorRespuestaJsonGemini::parse($text);
        });

        if ($response === null) {
            return null;
        }

        return GarmentAnalysisResult::fromJson($response);
    }
}
