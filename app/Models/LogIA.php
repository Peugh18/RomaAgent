<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogIA extends Model
{
    protected $table = 'logs_ia';

    protected $fillable = [
        'tipo',
        'phone_number',
        'modelo',
        'prompt',
        'respuesta',
        'tokens_entrada',
        'tokens_salida',
        'http_status',
        'error_mensaje',
        'error_codigo',
        'tiempo_respuesta_ms',
    ];

    protected $casts = [
        'tokens_entrada' => 'integer',
        'tokens_salida' => 'integer',
        'http_status' => 'integer',
        'tiempo_respuesta_ms' => 'decimal:2',
    ];

    /**
     * Registra un log de request a la API.
     */
    public static function registrarRequest(string $phoneNumber, string $modelo, string $prompt, int $tokensPrompt = 0): self
    {
        return self::create([
            'tipo' => 'request',
            'phone_number' => $phoneNumber,
            'modelo' => $modelo,
            'prompt' => substr($prompt, 0, 5000), // Truncar por seguridad
            'tokens_entrada' => $tokensPrompt,
        ]);
    }

    /**
     * Registra un log de respuesta exitosa.
     */
    public static function registrarResponse(int $logId, string $respuesta, int $tokensSalida = 0, float $tiempoMs = 0): self
    {
        $log = self::find($logId);
        if ($log) {
            $log->update([
                'tipo' => 'response',
                'respuesta' => substr($respuesta, 0, 5000),
                'tokens_salida' => $tokensSalida,
                'tiempo_respuesta_ms' => $tiempoMs,
            ]);
        }

        return $log;
    }

    /**
     * Registra un log de error.
     */
    public static function registrarError(string $phoneNumber, ?string $modelo, int $httpStatus, string $errorCodigo, string $errorMensaje, float $tiempoMs = 0): self
    {
        return self::create([
            'tipo' => 'error',
            'phone_number' => $phoneNumber,
            'modelo' => $modelo,
            'http_status' => $httpStatus,
            'error_codigo' => $errorCodigo,
            'error_mensaje' => substr($errorMensaje, 0, 5000),
            'tiempo_respuesta_ms' => $tiempoMs,
        ]);
    }

    /**
     * Obtiene estadísticas de uso de la IA.
     */
    public static function obtenerEstadisticas(int $ultimasHoras = 24): array
    {
        $desde = now()->subHours($ultimasHoras);

        $requests = self::where('created_at', '>=', $desde)->where('tipo', 'request')->count();
        $responses = self::where('created_at', '>=', $desde)->where('tipo', 'response')->count();
        $errores = self::where('created_at', '>=', $desde)->where('tipo', 'error')->count();

        $tokensEntrada = self::where('created_at', '>=', $desde)->sum('tokens_entrada') ?? 0;
        $tokensSalida = self::where('created_at', '>=', $desde)->sum('tokens_salida') ?? 0;

        return [
            'periodo_horas' => $ultimasHoras,
            'total_requests' => $requests,
            'total_responses' => $responses,
            'total_errores' => $errores,
            'tasa_exito' => $requests > 0 ? round(($responses / $requests) * 100, 2) : 0,
            'tokens_entrada' => $tokensEntrada,
            'tokens_salida' => $tokensSalida,
            'tokens_total' => $tokensEntrada + $tokensSalida,
        ];
    }

    /**
     * Obtiene el último error registrado.
     */
    public static function obtenerUltimoError(): ?self
    {
        return self::where('tipo', 'error')->latest()->first();
    }
}
