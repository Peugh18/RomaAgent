<?php

namespace App\Services;

use App\Models\CompanySetting;
use Illuminate\Support\Facades\Crypt;

class ConfiguracionAgente
{
    private CompanySetting $configuracion;

    public function __construct()
    {
        $this->configuracion = CompanySetting::first() ?? new CompanySetting();
    }

    /**
     * Verifica si el agente de IA está activado.
     */
    public function estaActivado(): bool
    {
        return $this->configuracion->agente_ia_activado ?? false;
    }

    /**
     * Obtiene el modelo de IA configurado.
     */
    public function obtenerModelo(): string
    {
        return $this->configuracion->agente_ia_modelo ?? 'gemini-2.5-flash';
    }

    /**
     * Obtiene la API key desencriptada.
     */
    public function obtenerApiKey(): ?string
    {
        $encriptada = $this->configuracion->agente_ia_api_key_encrypted;

        if (empty($encriptada)) {
            return null;
        }

        try {
            return Crypt::decryptString($encriptada);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Guarda la API key encriptada.
     */
    public function guardarApiKey(string $apiKey): void
    {
        $this->configuracion->agente_ia_api_key_encrypted = Crypt::encryptString($apiKey);
        $this->configuracion->save();
    }

    /**
     * Obtiene la temperatura de creatividad (0.0 - 1.0).
     */
    public function obtenerTemperatura(): float
    {
        return (float) ($this->configuracion->agente_ia_temperatura ?? 0.7);
    }

    /**
     * Lista de modelos disponibles para el agente.
     *
     * @return array<string, string>
     */
    public static function modelosDisponibles(): array
    {
        return [
            'gemini-2.5-flash' => 'Gemini 2.5 Flash (Google) - Recomendado plan gratuito, rápido',
            'gemini-2.5-pro' => 'Gemini 2.5 Pro (Google) - Requiere facturación, sin cuota free',
            'gemini-2.0-flash' => 'Gemini 2.0 Flash (Google) - Rápido y económico',
            'gemini-1.5-pro' => 'Gemini 1.5 Pro (Google) - Versión anterior, estable',
            'gpt-4o' => 'GPT-4o (OpenAI) - Alta calidad, más caro',
            'gpt-4o-mini' => 'GPT-4o Mini (OpenAI) - Balance calidad/precio',
            'claude-3-5-sonnet' => 'Claude 3.5 Sonnet (Anthropic) - Excelente para instrucciones',
        ];
    }

    /**
     * Obtiene todos los datos de configuración para la vista.
     */
    public function obtenerDatosCompletos(): array
    {
        return [
            'activado' => $this->estaActivado(),
            'modelo' => $this->obtenerModelo(),
            'temperatura' => $this->obtenerTemperatura(),
            'api_key_configurada' => ! empty($this->obtenerApiKey()),
            'modelos_disponibles' => self::modelosDisponibles(),
        ];
    }

    /**
     * Actualiza la configuración del agente.
     */
    public function actualizarConfiguracion(array $datos): void
    {
        $this->configuracion->agente_ia_activado = $datos['agente_ia_activado'] ?? false;
        $this->configuracion->agente_ia_modelo = $datos['agente_ia_modelo'] ?? 'gemini-2.5-flash';
        $this->configuracion->agente_ia_temperatura = $datos['agente_ia_temperatura'] ?? 0.7;

        if (! empty($datos['agente_ia_api_key'])) {
            $this->guardarApiKey($datos['agente_ia_api_key']);
        }

        $this->configuracion->save();
    }
}
