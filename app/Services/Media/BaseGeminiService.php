<?php

namespace App\Services\Media;

use App\Exceptions\GeminiQuotaExceededException;
use App\Services\ConfiguracionAgente;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Clase base para servicios que usan la API de Gemini.
 *
 * Proporciona funcionalidad común para:
 * - Obtener API key y modelo configurado
 * - Normalizar tipos MIME
 * - Manejar errores HTTP con retry
 * - Detectar errores de cuota (429)
 *
 * Esto elimina la duplicación de código entre ImageAnalyzer y AudioTranscriber.
 */
abstract class BaseGeminiService
{
    protected ConfiguracionAgente $configuracion;

    protected int $timeout = 45;

    protected int $maxRetries = 3;

    public function __construct(ConfiguracionAgente $configuracion)
    {
        $this->configuracion = $configuracion;
    }

    /**
     * Obtiene la API key de Gemini configurada.
     */
    protected function obtenerApiKey(): ?string
    {
        $apiKey = $this->configuracion->obtenerApiKey();

        if (empty($apiKey)) {
            Log::warning(static::class.': sin API key Gemini configurada');

            return null;
        }

        return $apiKey;
    }

    /**
     * Obtiene el modelo de Gemini configurado.
     */
    protected function obtenerModelo(): string
    {
        return $this->configuracion->obtenerModelo();
    }

    /**
     * Construye la URL del endpoint de Gemini (sin API key en query string).
     */
    protected function construirEndpoint(string $modelo): string
    {
        return sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent',
            $modelo
        );
    }

    /**
     * @return array<string, string>
     */
    protected function headersGemini(string $apiKey): array
    {
        return [
            'Content-Type' => 'application/json',
            'x-goog-api-key' => $apiKey,
        ];
    }

    /**
     * Normaliza tipos MIME de imágenes.
     */
    protected function normalizarMimeImagen(string $mime): string
    {
        $mapping = [
            'image/jpg' => 'image/jpeg',
            'image/pjpeg' => 'image/jpeg',
            'image/x-png' => 'image/png',
            'image/x-jpeg' => 'image/jpeg',
        ];

        return $mapping[$mime] ?? $mime;
    }

    /**
     * Normaliza tipos MIME de audio.
     */
    protected function normalizarMimeAudio(string $mime): string
    {
        $mapping = [
            'audio/mp3' => 'audio/mpeg',
            'audio/x-mp3' => 'audio/mpeg',
            'audio/wav' => 'audio/wav',
            'audio/x-wav' => 'audio/wav',
            'audio/ogg' => 'audio/ogg',
            'audio/aac' => 'audio/aac',
            'audio/m4a' => 'audio/mp4',
            'audio/x-m4a' => 'audio/mp4',
        ];

        return $mapping[$mime] ?? $mime;
    }

    /**
     * Ejecuta una llamada a la API de Gemini con retry.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T|null
     *
     * @throws GeminiQuotaExceededException
     */
    protected function ejecutarConRetry(callable $callback): mixed
    {
        $ultimoError = null;

        for ($intento = 1; $intento <= $this->maxRetries; $intento++) {
            try {
                return $callback();
            } catch (GeminiQuotaExceededException $e) {
                // Error 429: No reintentar, propagar inmediatamente
                throw $e;
            } catch (\Exception $e) {
                $ultimoError = $e;

                Log::warning(static::class.': Error en intento '.$intento.'/'.$this->maxRetries, [
                    'error' => $e->getMessage(),
                ]);

                if ($intento < $this->maxRetries) {
                    // Backoff exponencial: 1s, 2s, 4s
                    usleep((2 ** ($intento - 1)) * 1000000);
                }
            }
        }

        Log::error(static::class.': Todos los intentos fallaron', [
            'error' => $ultimoError?->getMessage(),
        ]);

        return null;
    }

    /**
     * Procesa la respuesta de la API de Gemini.
     *
     * @return array<string, mixed>|null
     *
     * @throws GeminiQuotaExceededException
     */
    protected function procesarRespuestaApi(Response $response): ?array
    {
        if (! $response->successful()) {
            $status = $response->status();
            $body = (string) $response->body();

            Log::error(static::class.': Gemini error', [
                'status' => $status,
                'body' => substr($body, 0, 300),
            ]);

            // Si es error 429 (cuota), lanzar excepción para que el job haga retry
            if ($status === 429) {
                $errorData = $response->json() ?? [];
                $mensaje = $errorData['error']['message'] ?? 'Cuota Gemini agotada';
                $retryAfter = (int) ($response->header('Retry-After') ?? 60);

                throw new GeminiQuotaExceededException($mensaje, $retryAfter);
            }

            return null;
        }

        return $response->json();
    }

    /**
     * Extrae el texto de la respuesta de Gemini.
     */
    protected function extraerTextoRespuesta(?array $data): ?string
    {
        if ($data === null) {
            return null;
        }

        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

        return is_string($text) ? trim($text) : null;
    }
}
