<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClienteGemini
{
    private string $apiKey;

    private string $modelo;

    private float $temperatura;

    private ?array $ultimoError = null;

    /** @var array<string, int>|null */
    private ?array $ultimoUso = null;

    private const BASE_URL = 'https://generativelanguage.googleapis.com/v1beta';

    public function __construct(string $apiKey, string $modelo = 'gemini-2.5-flash-lite', float $temperatura = 0.7)
    {
        $this->apiKey = $apiKey;
        $this->modelo = $modelo;
        $this->temperatura = $temperatura;
    }

    /**
     * @return array{http_status: int, codigo: int|string, mensaje: string, status: string}|null
     */
    public function obtenerUltimoError(): ?array
    {
        return $this->ultimoError;
    }

    /**
     * @return array<string, int>|null
     */
    public function obtenerUltimoUso(): ?array
    {
        return $this->ultimoUso;
    }

    /**
     * Genera una respuesta de texto a partir de un prompt.
     */
    public function generarRespuesta(string $promptSistema, array $historialMensajes): ?string
    {
        $this->ultimoError = null;
        $this->ultimoUso = null;

        try {
            $url = $this->urlGenerateContent();

            $contents = $this->construirContents($historialMensajes);

            $response = Http::withHeaders($this->headersApi())
                ->timeout(30)
                ->connectTimeout(10)
                ->retry(2, 200, throw: false)
                ->post($url, [
                    'systemInstruction' => [
                        'parts' => [['text' => $promptSistema]],
                    ],
                    'contents' => $contents,
                    'generationConfig' => [
                        'temperature' => $this->temperatura,
                        'maxOutputTokens' => 2048,
                        'topP' => 0.95,
                        'topK' => 40,
                    ],
                ]);

            if (! $response->successful()) {
                $errorBody = $response->body();
                $errorData = $response->json() ?? [];

                $this->ultimoError = [
                    'http_status' => $response->status(),
                    'codigo' => $errorData['error']['code'] ?? $response->status(),
                    'mensaje' => $errorData['error']['message'] ?? $errorBody,
                    'status' => $errorData['error']['status'] ?? 'ERROR',
                ];

                Log::error('Gemini API error detallado', [
                    'status' => $response->status(),
                    'url' => str_replace($this->apiKey, '***KEY***', $url),
                    'error_body' => $errorBody,
                    'error_message' => $errorData['error']['message'] ?? 'Sin mensaje',
                    'error_code' => $errorData['error']['code'] ?? 'Sin código',
                    'error_status' => $errorData['error']['status'] ?? 'Sin status',
                    'prompt_length' => strlen($promptSistema),
                    'historial_count' => count($historialMensajes),
                ]);

                return null;
            }

            $data = $response->json();

            $this->ultimoUso = is_array($data['usageMetadata'] ?? null) ? $data['usageMetadata'] : null;

            Log::debug('Gemini API respuesta exitosa', [
                'candidates_count' => count($data['candidates'] ?? []),
                'usage' => $data['usageMetadata'] ?? 'No disponible',
            ]);

            $texto = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

            if ($texto === null) {
                Log::warning('Gemini respuesta sin texto', [
                    'response_structure' => array_keys($data),
                    'candidates' => $data['candidates'] ?? 'No candidates',
                    'finishReason' => $data['candidates'][0]['finishReason'] ?? 'No finish reason',
                ]);

                return null;
            }

            return $this->limpiarRespuesta($texto);
        } catch (\Exception $e) {
            Log::error('Gemini exception', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Ejecuta un turno con herramientas (function calling) hasta obtener texto final o agotar iteraciones.
     *
     * @param  list<array{name: string, description: string, parameters: array<string, mixed>}>  $herramientas
     * @param  callable(string, array<string, mixed>): array<string, mixed>  $ejecutor
     */
    public function generarConHerramientas(
        string $promptSistema,
        array $historialMensajes,
        array $herramientas,
        callable $ejecutor,
        int $maxIteraciones = 6,
        ?array $toolConfig = null,
    ): ?ResultadoGeminiAgente {
        $this->ultimoError = null;
        $this->ultimoUso = null;

        $contents = $this->construirContents($historialMensajes);
        $toolsPayload = [[
            'functionDeclarations' => array_map(fn (array $tool): array => [
                'name' => $tool['name'],
                'description' => $tool['description'],
                'parameters' => $this->normalizarEsquemaHerramienta($tool['parameters']),
            ], $herramientas),
        ]];

        $iteracion = 0;

        try {
            while ($iteracion < $maxIteraciones) {
                $iteracion++;
                $configHerramientas = ($iteracion === 1 && $toolConfig !== null) ? $toolConfig : null;
                $data = $this->postGenerateContent($promptSistema, $contents, $toolsPayload, $configHerramientas);

                if ($data === null) {
                    return null;
                }

                $parts = $data['candidates'][0]['content']['parts'] ?? [];
                $functionCall = null;
                $texto = null;

                foreach ($parts as $part) {
                    if (isset($part['functionCall'])) {
                        $functionCall = $part['functionCall'];
                    }
                    if (isset($part['text']) && is_string($part['text'])) {
                        $texto = trim($part['text']);
                    }
                }

                if ($functionCall === null && ($texto === null || $texto === '')) {
                    Log::warning('Gemini agent devolvio candidato sin text ni functionCall', [
                        'response' => $data,
                    ]);
                }

                if ($functionCall !== null) {
                    $nombre = (string) ($functionCall['name'] ?? '');
                    $args = is_array($functionCall['args'] ?? null) ? $functionCall['args'] : [];

                    $functionCall = $this->normalizarFunctionCall($functionCall);

                    Log::info('Gemini function call', [
                        'tool' => $nombre,
                        'args' => $args,
                        'iteracion' => $iteracion,
                    ]);

                    $contents[] = [
                        'role' => 'model',
                        'parts' => [['functionCall' => $functionCall]],
                    ];

                    $resultado = $ejecutor($nombre, $args);

                    if (is_array($resultado) && empty($resultado)) {
                        $resultado = new \stdClass;
                    }

                    $contents[] = [
                        'role' => 'user',
                        'parts' => [[
                            'functionResponse' => [
                                'name' => $nombre,
                                'response' => $resultado,
                            ],
                        ]],
                    ];

                    continue;
                }

                if ($texto !== null && $texto !== '') {
                    return new ResultadoGeminiAgente(
                        texto: $this->limpiarRespuesta($texto),
                        iteraciones: $iteracion,
                    );
                }

                break;
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Gemini agent exception', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $contents
     * @param  array<int, array<string, mixed>>|null  $tools
     * @return array<string, mixed>|null
     */
    private function postGenerateContent(
        string $promptSistema,
        array $contents,
        ?array $tools = null,
        ?array $toolConfig = null,
    ): ?array {
        $url = $this->urlGenerateContent();

        $payload = [
            'systemInstruction' => [
                'parts' => [['text' => $promptSistema]],
            ],
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => $this->temperatura,
                'maxOutputTokens' => 2048,
                'topP' => 0.95,
                'topK' => 40,
            ],
        ];

        if ($tools !== null) {
            $payload['tools'] = $tools;
        }

        if ($toolConfig !== null) {
            $payload['toolConfig'] = $toolConfig;
        }

        $response = Http::withHeaders($this->headersApi())
            ->timeout(45)
            ->connectTimeout(10)
            ->retry(2, 200, throw: false)
            ->post($url, $payload);

        if (! $response->successful()) {
            $errorData = $response->json() ?? [];
            $this->ultimoError = [
                'http_status' => $response->status(),
                'codigo' => $errorData['error']['code'] ?? $response->status(),
                'mensaje' => $errorData['error']['message'] ?? $response->body(),
                'status' => $errorData['error']['status'] ?? 'ERROR',
            ];

            Log::error('Gemini API error (agent)', $this->ultimoError);

            return null;
        }

        $data = $response->json();
        $this->ultimoUso = is_array($data['usageMetadata'] ?? null) ? $data['usageMetadata'] : null;

        return is_array($data) ? $data : null;
    }

    /**
     * Gemini exige que `properties` sea un mapa JSON ({}), no una lista ([]).
     *
     * @param  array<string, mixed>  $esquema
     * @return array<string, mixed>|\stdClass
     */
    private function normalizarEsquemaHerramienta(array $esquema): array|\stdClass
    {
        if (isset($esquema['properties']) && is_array($esquema['properties']) && $esquema['properties'] === []) {
            $esquema['properties'] = new \stdClass;
        }

        if (isset($esquema['properties']) && is_array($esquema['properties'])) {
            foreach ($esquema['properties'] as $nombre => $definicion) {
                if (is_array($definicion) && ($definicion['type'] ?? null) === 'object') {
                    $esquema['properties'][$nombre] = $this->normalizarEsquemaHerramienta($definicion);
                }
            }
        }

        return $esquema;
    }

    /**
     * Gemini exige que args vacíos sean {} y no [].
     *
     * @param  array<string, mixed>  $functionCall
     * @return array{name: string, args: array<string, mixed>|\stdClass}
     */
    private function normalizarFunctionCall(array $functionCall): array
    {
        return [
            'name' => (string) ($functionCall['name'] ?? ''),
            'args' => $this->normalizarFunctionCallArgs($functionCall['args'] ?? []),
        ];
    }

    /**
     * @return array<string, mixed>|\stdClass
     */
    private function normalizarFunctionCallArgs(mixed $args): array|\stdClass
    {
        if (! is_array($args) || $args === []) {
            return new \stdClass;
        }

        return $args;
    }

    /**
     * Construye el array de contents para la API de Gemini.
     */
    private function construirContents(array $historialMensajes): array
    {
        $contents = [];
        $lastRole = null;
        $lastText = '';

        foreach ($historialMensajes as $mensaje) {
            $role = $mensaje['role'] === 'assistant' ? 'model' : 'user';
            $text = trim((string) $mensaje['content']);

            if ($text === '') {
                continue;
            }

            if ($role === $lastRole) {
                // Merge text
                $lastText .= "\n\n".$text;
                // Update last content
                $contents[count($contents) - 1]['parts'][0]['text'] = $lastText;
            } else {
                $contents[] = [
                    'role' => $role,
                    'parts' => [['text' => $text]],
                ];
                $lastRole = $role;
                $lastText = $text;
            }
        }

        if ($contents === []) {
            $contents[] = [
                'role' => 'user',
                'parts' => [['text' => 'Hola']],
            ];
        }

        return $contents;
    }

    /**
     * Extrae segundos de espera sugeridos por Gemini en errores 429.
     */
    public static function segundosReintentoDesdeError(string $mensaje): int
    {
        if (preg_match('/retry in (\d+(?:\.\d+)?)\s*s/i', $mensaje, $matches) === 1) {
            return max(15, (int) ceil((float) $matches[1]));
        }

        return 60;
    }

    /**
     * Limpia la respuesta de la IA para WhatsApp.
     */
    private function limpiarRespuesta(string $texto): string
    {
        // Quitar pseudo-pensamientos en cursiva simple *texto* (no tocar **negrita**)
        $texto = preg_replace('/(?<!\*)\*(?!\*)([^*\n]+?)\*(?!\*)/u', '', $texto) ?? $texto;

        // Preservar saltos de línea; normalizar espacios solo dentro de cada línea
        $lineas = preg_split('/\R/u', $texto) ?: [];
        $lineas = array_map(
            static fn (string $linea): string => trim(preg_replace('/[ \t]+/u', ' ', $linea) ?? $linea),
            $lineas,
        );

        $texto = trim(implode("\n", $lineas));

        // Colapsar más de 2 líneas vacías seguidas
        return preg_replace("/\n{3,}/u", "\n\n", $texto) ?? $texto;
    }

    /**
     * @return array<string, string>
     */
    private function headersApi(): array
    {
        return [
            'Content-Type' => 'application/json',
            'x-goog-api-key' => $this->apiKey,
        ];
    }

    private function urlGenerateContent(): string
    {
        return sprintf('%s/models/%s:generateContent', self::BASE_URL, $this->modelo);
    }
}
