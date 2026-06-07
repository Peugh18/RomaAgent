<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LogIA;
use App\Services\AlertaCuotaGemini;
use App\Services\ClienteGemini;
use App\Services\ConfiguracionAgente;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class EstadoIAController extends Controller
{
    public function __construct(private AlertaCuotaGemini $alertaCuota) {}

    /**
     * Estado ligero de la alerta de cuota (para polling en el panel).
     */
    public function alertaCuota(): JsonResponse
    {
        return response()->json([
            'alerta' => $this->alertaCuota->obtener(),
        ]);
    }

    /**
     * Verifica el estado de la configuración de IA.
     */
    public function verificar(): JsonResponse
    {
        $configuracion = new ConfiguracionAgente;

        $estado = [
            'activado' => $configuracion->estaActivado(),
            'modelo_configurado' => $configuracion->obtenerModelo(),
            'api_key_configurada' => ! empty($configuracion->obtenerApiKey()),
            'temperatura' => $configuracion->obtenerTemperatura(),
        ];

        // Verificar conexión con Gemini si hay API key (cache 5 min para no gastar cuota)
        $pruebaApi = null;
        if ($estado['api_key_configurada']) {
            $pruebaApi = Cache::remember(
                'estado_ia_prueba_gemini',
                300,
                fn (): array => $this->probarConexionGemini($configuracion),
            );
        }

        // Estadísticas de uso
        $estadisticas = LogIA::obtenerEstadisticas(24);

        // Último error si existe
        $ultimoError = LogIA::obtenerUltimoError();

        return response()->json([
            'configuracion' => $estado,
            'prueba_api' => $pruebaApi,
            'estadisticas_24h' => $estadisticas,
            'ultimo_error' => $ultimoError ? [
                'codigo' => $ultimoError->error_codigo,
                'mensaje' => $ultimoError->error_mensaje,
                'fecha' => $ultimoError->created_at->toIso8601String(),
            ] : null,
            'alerta_cuota' => $this->alertaCuota->obtener(),
        ]);
    }

    /**
     * Prueba la conexión con la API de Gemini.
     */
    private function probarConexionGemini(ConfiguracionAgente $configuracion): array
    {
        $inicio = microtime(true);

        try {
            $cliente = new ClienteGemini(
                apiKey: $configuracion->obtenerApiKey(),
                modelo: $configuracion->obtenerModelo(),
                temperatura: 0.1
            );

            // Hacer una prueba simple
            $respuesta = $cliente->generarRespuesta(
                'Eres un asistente. Responde "OK" si funciona.',
                [['role' => 'user', 'content' => 'Di OK']]
            );

            $tiempoMs = round((microtime(true) - $inicio) * 1000, 2);

            if ($respuesta !== null) {
                $this->alertaCuota->limpiar();

                return [
                    'exitosa' => true,
                    'tiempo_ms' => $tiempoMs,
                    'respuesta' => $respuesta,
                    'error' => null,
                ];
            }

            $errorGemini = $cliente->obtenerUltimoError();

            if ((int) ($errorGemini['http_status'] ?? 0) === 429) {
                $mensaje = (string) ($errorGemini['mensaje'] ?? 'Cuota Gemini agotada');
                $this->alertaCuota->marcar(
                    $mensaje,
                    ClienteGemini::segundosReintentoDesdeError($mensaje),
                );
            }

            return [
                'exitosa' => false,
                'tiempo_ms' => $tiempoMs,
                'respuesta' => null,
                'error' => $errorGemini['mensaje'] ?? 'La API respondió pero sin texto',
                'http_status' => $errorGemini['http_status'] ?? null,
                'error_status' => $errorGemini['status'] ?? null,
            ];
        } catch (\Exception $e) {
            $tiempoMs = round((microtime(true) - $inicio) * 1000, 2);

            return [
                'exitosa' => false,
                'tiempo_ms' => $tiempoMs,
                'respuesta' => null,
                'error' => $e->getMessage(),
            ];
        }
    }
}
