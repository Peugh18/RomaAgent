<?php

namespace App\Services;

use App\Models\AgenteConfig;
use App\Models\CompanySetting;
use Illuminate\Support\Facades\Crypt;

/**
 * Servicio de configuración del Agente IA
 *
 * AHORA USA: Tabla AgenteConfig (relación con CompanySetting)
 * ANTES: Campos legacy en CompanySetting (agente_ia_activado, agente_ia_modelo, etc.)
 *
 * Esta refactorización separa la configuración de IA del monolito CompanySetting,
 * permitiendo mejor organización y caché granular.
 */
class ConfiguracionAgente
{
    private ?AgenteConfig $agenteConfig = null;

    public function __construct()
    {
        // Obtener o crear configuración del agente vinculada al CompanySetting principal
        $companySetting = CompanySetting::first();

        if ($companySetting) {
            $this->agenteConfig = $companySetting->obtenerOCrearAgente();
        }
    }

    /**
     * Verifica si el agente de IA está activado.
     */
    public function estaActivado(): bool
    {
        return $this->agenteConfig?->activado ?? false;
    }

    /**
     * Obtiene el modelo de IA configurado.
     */
    public function obtenerModelo(): string
    {
        $modelo = (string) ($this->agenteConfig?->modelo ?? 'gemini-2.5-flash');

        if (! str_starts_with($modelo, 'gemini-')) {
            return 'gemini-2.5-flash';
        }

        return $modelo;
    }

    /**
     * Obtiene la API key desencriptada.
     */
    public function obtenerApiKey(): ?string
    {
        return $this->agenteConfig?->obtenerApiKey();
    }

    /**
     * Guarda la API key encriptada.
     */
    public function guardarApiKey(string $apiKey): void
    {
        if ($this->agenteConfig) {
            $this->agenteConfig->api_key_encrypted = Crypt::encryptString($apiKey);
            $this->agenteConfig->save();
        }
    }

    /**
     * Obtiene la temperatura de creatividad (0.0 - 1.0).
     */
    public function obtenerTemperatura(): float
    {
        return (float) ($this->agenteConfig?->temperatura ?? 0.3);
    }

    /**
     * Obtiene el tono del bot.
     */
    public function obtenerTono(): ?string
    {
        return $this->agenteConfig?->tono_bot;
    }

    /**
     * Obtiene el estilo de comunicación.
     */
    public function obtenerEstiloComunicacion(): ?string
    {
        return $this->agenteConfig?->estilo_comunicacion;
    }

    /**
     * Obtiene la personalidad del bot.
     */
    public function obtenerPersonalidad(): ?string
    {
        return $this->agenteConfig?->personalidad_bot;
    }

    /**
     * Obtiene la respuesta cuando detecta que es un bot.
     */
    public function obtenerRespuestaSiEsBot(): ?string
    {
        return $this->agenteConfig?->respuesta_si_es_bot;
    }

    /**
     * Lista de modelos disponibles para el agente.
     *
     * @return array<string, string>
     */
    public static function modelosDisponibles(): array
    {
        return [
            'gemini-2.5-flash' => 'Gemini 2.5 Flash — recomendado (rápido y económico)',
            'gemini-2.0-flash' => 'Gemini 2.0 Flash — muy económico',
            'gemini-2.0-flash-lite' => 'Gemini 2.0 Flash-Lite — máximo ahorro de tokens',
            'gemini-1.5-flash' => 'Gemini 1.5 Flash — estable, bajo costo',
        ];
    }

    /**
     * Obtiene todos los datos de configuración para la vista.
     *
     * @return array<string, mixed>
     */
    public function obtenerDatosCompletos(): array
    {
        return [
            'activado' => $this->estaActivado(),
            'modelo' => $this->obtenerModelo(),
            'temperatura' => $this->obtenerTemperatura(),
            'api_key_configurada' => ! empty($this->obtenerApiKey()),
            'modelos_disponibles' => self::modelosDisponibles(),
            // Nuevos campos disponibles en la tabla separada:
            'tono_bot' => $this->obtenerTono(),
            'estilo_comunicacion' => $this->obtenerEstiloComunicacion(),
            'personalidad_bot' => $this->obtenerPersonalidad(),
            'respuesta_si_es_bot' => $this->obtenerRespuestaSiEsBot(),
        ];
    }

    /**
     * Actualiza la configuración del agente.
     *
     * AHORA: Guarda en tabla AgenteConfig (via relación)
     * ANTES: Guardaba en CompanySetting directamente
     */
    public function actualizarConfiguracion(array $datos): void
    {
        if (! $this->agenteConfig) {
            // Crear CompanySetting y AgenteConfig si no existen
            $companySetting = CompanySetting::firstOrCreate([]);
            $this->agenteConfig = $companySetting->obtenerOCrearAgente();
        }

        $updateData = [
            'activado' => $datos['agente_ia_activado'] ?? $this->agenteConfig->activado ?? false,
            'modelo' => $datos['agente_ia_modelo'] ?? $this->agenteConfig->modelo ?? 'gemini-2.5-flash',
            'temperatura' => $datos['agente_ia_temperatura'] ?? $this->agenteConfig->temperatura ?? 0.3,
        ];

        // Actualizar API key si se proporcionó
        if (! empty($datos['agente_ia_api_key'])) {
            $updateData['api_key_encrypted'] = Crypt::encryptString($datos['agente_ia_api_key']);
        }

        $this->agenteConfig->update($updateData);
    }

    /**
     * Activa el agente IA.
     */
    public function activar(): void
    {
        $this->agenteConfig?->activar();
    }

    /**
     * Desactiva el agente IA.
     */
    public function desactivar(): void
    {
        $this->agenteConfig?->desactivar();
    }
}
