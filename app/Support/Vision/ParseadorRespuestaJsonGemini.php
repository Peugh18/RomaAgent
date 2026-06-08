<?php

namespace App\Support\Vision;

class ParseadorRespuestaJsonGemini
{
    /**
     * @return array<string, mixed>|null
     */
    public static function parse(?string $text): ?array
    {
        if ($text === null || trim($text) === '') {
            return null;
        }

        $text = self::limpiarTexto($text);

        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return self::enriquecerDesdeTextoCrudo($decoded, $text);
        }

        $reparado = self::repararJsonTruncado($text);
        if ($reparado !== null) {
            return self::enriquecerDesdeTextoCrudo($reparado, $text);
        }

        return self::extraerCamposSueltos($text);
    }

    /**
     * @param  array<string, mixed>  $profile
     * @return array<string, mixed>
     */
    private static function enriquecerDesdeTextoCrudo(array $profile, string $text): array
    {
        if (! isset($profile['es_captura_redes']) && str_contains($text, '"es_captura_red')) {
            $profile['es_captura_redes'] = true;
        }

        return $profile;
    }

    private static function limpiarTexto(string $text): string
    {
        $text = trim($text);
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text) ?? $text;
        $text = preg_replace('/\s*```\s*$/', '', $text) ?? $text;

        return trim($text);
    }

    /**
     * Intenta cerrar llaves/corchetes faltantes cuando Gemini corta el JSON.
     *
     * @return array<string, mixed>|null
     */
    private static function repararJsonTruncado(string $text): ?array
    {
        $candidato = rtrim($text, ", \n\r\t");

        if (preg_match('/"[^"]*$/', $candidato) === 1) {
            $candidato = preg_replace('/,?\s*"[^"]*$/', '', $candidato) ?? $candidato;
        }

        $candidato = rtrim($candidato, ", \n\r\t");

        $opens = substr_count($candidato, '{') - substr_count($candidato, '}');
        $openBrackets = substr_count($candidato, '[') - substr_count($candidato, ']');

        if ($opens <= 0 && $openBrackets <= 0) {
            return null;
        }

        $candidato .= str_repeat(']', max(0, $openBrackets));
        $candidato .= str_repeat('}', max(0, $opens));

        $decoded = json_decode($candidato, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function extraerCamposSueltos(string $text): ?array
    {
        $campos = [];

        if (preg_match('/"tipo"\s*:\s*"([^"]+)"/', $text, $match) === 1) {
            $campos['tipo'] = $match[1];
        }

        if (preg_match('/"es_comprobante"\s*:\s*(true|false)/', $text, $match) === 1) {
            $campos['es_comprobante'] = $match[1] === 'true';
        }

        if (preg_match('/"es_captura_redes"\s*:\s*(true|false)/', $text, $match) === 1) {
            $campos['es_captura_redes'] = $match[1] === 'true';
        } elseif (str_contains($text, '"es_captura_red')) {
            $campos['es_captura_redes'] = true;
        }

        if (preg_match('/"tipo_prenda"\s*:\s*"([^"]+)"/', $text, $match) === 1) {
            $campos['tipo_prenda'] = $match[1];
        }

        if (preg_match('/"color_dominante"\s*:\s*"([^"]+)"/', $text, $match) === 1) {
            $campos['color_dominante'] = $match[1];
            $campos['colores_dominantes'] = [$match[1]];
        }

        if (preg_match('/"patron"\s*:\s*"([^"]+)"/', $text, $match) === 1) {
            $campos['patron'] = $match[1];
        }

        if (preg_match('/"descripcion_prenda"\s*:\s*"([^"]+)"/', $text, $match) === 1) {
            $campos['descripcion_prenda'] = $match[1];
        }

        return $campos === [] ? null : $campos;
    }
}
