<?php

namespace App\Support\PromptBuilder;

/**
 * Builder para construir prompts del agente IA de forma desacoplada.
 * Cada método construye una sección independiente que puede combinarse.
 */
class PromptBuilder
{
    private array $secciones = [];

    public function addSection(string $titulo, string $contenido, int $nivel = 2): self
    {
        $prefijo = str_repeat('#', $nivel);
        $this->secciones[] = "{$prefijo} {$titulo}\n\n{$contenido}";

        return $this;
    }

    public function addRaw(string $contenido): self
    {
        $this->secciones[] = $contenido;

        return $this;
    }

    public function build(): string
    {
        return implode("\n\n---\n\n", array_filter($this->secciones));
    }

    public function reset(): self
    {
        $this->secciones = [];

        return $this;
    }

    /**
     * Construye sección de identidad y personalidad del bot.
     */
    public static function buildIdentidadSection(
        string $nombre,
        string $actividad,
        string $tono = 'cálido y cercano',
        string $estilo = 'natural',
        ?string $descripcion = null,
        ?string $respuestaBot = null,
    ): string {
        if ($descripcion !== null && $descripcion !== '') {
            $texto = "Te presentas siempre como **{$nombre}**.\n\n{$descripcion}";
        } else {
            $texto = <<<IDENTIDAD
Te presentas siempre como **{$nombre}**.
Eres un asistente de ventas experto en **{$actividad}**.
- Tono: {$tono}
- Estilo: {$estilo}
- Objetivo: Vender y ayudar al cliente de manera natural y cercana
IDENTIDAD;
        }

        if ($respuestaBot !== null && $respuestaBot !== '') {
            $texto .= "\n\nSi te preguntan directamente si eres bot o IA, responde:\n\"{$respuestaBot}\"";
        }

        return $texto;
    }

    /**
     * Construye sección de métodos de pago.
     */
    public static function buildMetodosPagoSection(array $metodos, string $moneda = 'PEN'): string
    {
        if (empty($metodos)) {
            return 'No hay métodos de pago configurados';
        }

        $lineas = [];
        foreach ($metodos as $metodo) {
            $nombre = $metodo['nombre'] ?? 'Método';
            $destinatario = $metodo['destinatario'] ?? '';
            $numero = $metodo['numero_cuenta'] ?? '';
            $descripcion = $metodo['descripcion'] ?? '';

            $partes = ["- {$nombre}"];
            if ($destinatario !== '') {
                $partes[] = "Titular: {$destinatario}";
            }
            if ($numero !== '') {
                $partes[] = "Número: {$numero}";
            }
            if ($descripcion !== '') {
                $partes[] = "{$descripcion}";
            }

            $lineas[] = implode(' | ', $partes);
        }

        return implode("\n", $lineas);
    }

    /**
     * Construye sección de recordatorios.
     */
    public static function buildRecordatoriosSection(
        string $recordatorio3min,
        string $recordatorio15min,
        ?string $recordatorioDatos = null,
    ): string {
        $lineas = [
            "- **Después de 3 minutos sin respuesta:** \"{$recordatorio3min}\"",
            "- **Después de 15 minutos sin respuesta:** \"{$recordatorio15min}\"",
        ];

        if ($recordatorioDatos !== null && $recordatorioDatos !== '') {
            $lineas[] = "- **Si no envía datos completos después de 15 minutos:** \"{$recordatorioDatos}\"";
        }

        return implode("\n", $lineas);
    }
}
