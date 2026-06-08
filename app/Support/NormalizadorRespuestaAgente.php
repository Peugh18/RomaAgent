<?php

namespace App\Support;

use App\Models\Sale;
use Illuminate\Support\Facades\Log;

/**
 * Post-procesa respuestas del agente antes de enviarlas por WhatsApp.
 */
class NormalizadorRespuestaAgente
{
    public const SEPARADOR_MENSAJES = '---SPLIT---';

    /** Máximo de burbujas WhatsApp por respuesta del agente. */
    public const MAX_PARTES = 3;

    /** Por encima de este largo el sistema intenta partir aunque la IA no use SPLIT. */
    public const UMBRAL_TEXTO_LARGO = 180;

    /** Objetivo de caracteres por burbuja al partir automáticamente. */
    public const MAX_CARACTERES_POR_PARTE = 240;

    public function procesar(string $texto, string $moneda = 'PEN', ?Sale $sale = null): string
    {
        $texto = trim($texto);
        if ($texto === '') {
            return $texto;
        }

        $simbolo = FormateadorCatalogoProductos::simboloDesdeMoneda($moneda);
        $texto = $this->normalizarMoneda($texto, $moneda, $simbolo);
        $texto = $this->normalizarEtiquetasTalla($texto);

        if ($sale !== null) {
            $texto = $this->corregirTotalUnidadIncorrecta($texto, $sale, $simbolo);
        }

        return $texto;
    }

    /**
     * Divide la respuesta en burbujas WhatsApp (máx. {@see MAX_PARTES}).
     *
     * @return list<string>
     */
    public function partirEnMensajes(string $texto): array
    {
        $texto = trim($texto);
        if ($texto === '') {
            return [];
        }

        $partesExplicitas = $this->partesDesdeSeparadoresExplicitos($texto);
        if (count($partesExplicitas) >= 2) {
            return $this->limitarPartes($partesExplicitas);
        }

        if (mb_strlen($texto) <= self::UMBRAL_TEXTO_LARGO) {
            return [$texto];
        }

        $porParrafos = $this->partirPorParrafos($texto);
        if (count($porParrafos) >= 2) {
            return $this->limitarPartes($porParrafos);
        }

        $porOraciones = $this->agruparOracionesEnPartes($this->extraerOraciones($texto));
        if (count($porOraciones) >= 2) {
            return $this->limitarPartes($porOraciones);
        }

        return $this->limitarPartes([$texto]);
    }

    /**
     * @return list<string>
     */
    private function partesDesdeSeparadoresExplicitos(string $texto): array
    {
        if (str_contains($texto, self::SEPARADOR_MENSAJES)) {
            return $this->partesDesdeSeparadorCanonico($texto);
        }

        $partes = preg_split('/\R\s*-{3,}\s*\R/u', $texto) ?: [];

        return array_values(array_filter(array_map(
            static fn (string $parte): string => trim($parte),
            $partes,
        ), static fn (string $parte): bool => $parte !== ''));
    }

    /**
     * @return list<string>
     */
    private function partesDesdeSeparadorCanonico(string $texto): array
    {
        return array_values(array_filter(array_map(
            static fn (string $parte): string => trim($parte),
            explode(self::SEPARADOR_MENSAJES, $texto),
        ), static fn (string $parte): bool => $parte !== ''));
    }

    /**
     * @return list<string>
     */
    private function partirPorParrafos(string $texto): array
    {
        $bloques = preg_split("/\n{2,}/u", $texto) ?: [];
        $bloques = array_values(array_filter(array_map('trim', $bloques), static fn (string $b): bool => $b !== ''));

        if (count($bloques) <= 1) {
            return [$texto];
        }

        $partes = [];
        $actual = '';

        foreach ($bloques as $bloque) {
            if ($actual === '') {
                $actual = $bloque;

                continue;
            }

            if (mb_strlen($actual."\n\n".$bloque) <= self::MAX_CARACTERES_POR_PARTE) {
                $actual .= "\n\n".$bloque;

                continue;
            }

            $partes[] = $actual;
            $actual = $bloque;
        }

        if ($actual !== '') {
            $partes[] = $actual;
        }

        return $partes;
    }

    /**
     * @return list<string>
     */
    private function extraerOraciones(string $texto): array
    {
        $normalizado = preg_replace("/\s+/u", ' ', $texto) ?? $texto;
        $fragmentos = preg_split('/(?<=[.!?…])\s+/u', trim($normalizado)) ?: [];

        $oraciones = array_values(array_filter(array_map('trim', $fragmentos), static fn (string $o): bool => $o !== ''));

        if ($oraciones !== []) {
            return $oraciones;
        }

        return [trim($normalizado)];
    }

    /**
     * @param  list<string>  $oraciones
     * @return list<string>
     */
    private function agruparOracionesEnPartes(array $oraciones): array
    {
        if ($oraciones === []) {
            return [];
        }

        if (count($oraciones) === 1 && mb_strlen($oraciones[0]) > self::MAX_CARACTERES_POR_PARTE) {
            return $this->partirPorPalabras($oraciones[0]);
        }

        $partes = [];
        $actual = '';

        foreach ($oraciones as $oracion) {
            if ($actual === '') {
                $actual = $oracion;

                continue;
            }

            $candidato = $actual.' '.$oracion;

            if (mb_strlen($candidato) <= self::MAX_CARACTERES_POR_PARTE && count($partes) < self::MAX_PARTES - 1) {
                $actual = $candidato;

                continue;
            }

            $partes[] = $actual;
            $actual = $oracion;
        }

        if ($actual !== '') {
            $partes[] = $actual;
        }

        return $partes;
    }

    /**
     * @return list<string>
     */
    private function partirPorPalabras(string $texto): array
    {
        $palabras = preg_split('/\s+/u', trim($texto)) ?: [];
        $partes = [];
        $actual = '';

        foreach ($palabras as $palabra) {
            if ($actual === '') {
                $actual = $palabra;

                continue;
            }

            $candidato = $actual.' '.$palabra;

            if (mb_strlen($candidato) <= self::MAX_CARACTERES_POR_PARTE && count($partes) < self::MAX_PARTES - 1) {
                $actual = $candidato;

                continue;
            }

            $partes[] = $actual;
            $actual = $palabra;
        }

        if ($actual !== '') {
            $partes[] = $actual;
        }

        return $partes !== [] ? $partes : [$texto];
    }

    /**
     * @param  list<string>  $partes
     * @return list<string>
     */
    private function limitarPartes(array $partes): array
    {
        $partes = array_values(array_filter(array_map('trim', $partes), static fn (string $p): bool => $p !== ''));

        if ($partes === []) {
            return [];
        }

        while (count($partes) > self::MAX_PARTES) {
            $indiceFusion = $this->indiceParMasCorto($partes);
            $partes[$indiceFusion] = trim($partes[$indiceFusion].' '.$partes[$indiceFusion + 1]);
            array_splice($partes, $indiceFusion + 1, 1);
        }

        return $partes;
    }

    /**
     * @param  list<string>  $partes
     */
    private function indiceParMasCorto(array $partes): int
    {
        $mejorIndice = 0;
        $menorLongitud = PHP_INT_MAX;

        for ($i = 0; $i < count($partes) - 1; $i++) {
            $longitud = mb_strlen($partes[$i]) + mb_strlen($partes[$i + 1]);
            if ($longitud < $menorLongitud) {
                $menorLongitud = $longitud;
                $mejorIndice = $i;
            }
        }

        return $mejorIndice;
    }

    public function normalizarMoneda(string $texto, string $moneda, ?string $simbolo = null): string
    {
        if ($moneda === 'USD') {
            return $texto;
        }

        $simbolo ??= FormateadorCatalogoProductos::simboloDesdeMoneda($moneda);

        $texto = preg_replace('/\$\s*(\d+(?:[.,]\d{1,2})?)/u', $simbolo.' $1', $texto) ?? $texto;
        $texto = preg_replace('/USD\s*(\d+(?:[.,]\d{1,2})?)/iu', $simbolo.' $1', $texto) ?? $texto;
        $texto = preg_replace('/(\d+(?:[.,]\d{1,2})?)\s*(?:USD|dólares|dolares|dólar|dolar)/iu', $simbolo.' $1', $texto) ?? $texto;

        return $texto;
    }

    public function normalizarEtiquetasTalla(string $texto): string
    {
        $reemplazos = [
            '/\btalla\s+única\b/iu' => 'talla estándar',
            '/\btalla\s+unica\b/iu' => 'talla estándar',
            '/\búnica\b/iu' => 'talla estándar',
            '/\bunica\b/iu' => 'talla estándar',
            '/\bUNICA\b/u' => 'talla estándar',
        ];

        foreach ($reemplazos as $patron => $reemplazo) {
            $texto = preg_replace($patron, $reemplazo, $texto) ?? $texto;
        }

        return $texto;
    }

    public function corregirTotalUnidadIncorrecta(string $texto, Sale $sale, string $simbolo): string
    {
        $quantity = max(1, (int) $sale->quantity);
        $totalCorrecto = (float) $sale->total_amount;

        if ($quantity <= 1 || $totalCorrecto <= 0) {
            return $texto;
        }

        $unitPrice = (float) $sale->unit_price;
        $deliveryCost = (float) $sale->delivery_cost;
        $totalUnaUnidad = $unitPrice + $deliveryCost;

        if (abs($totalUnaUnidad - $totalCorrecto) < 0.01) {
            return $texto;
        }

        $montos = $this->extraerMontos($texto);
        $tieneTotalIncorrecto = false;

        foreach ($montos as $monto) {
            if (abs($monto - $totalUnaUnidad) < 0.01 && abs($monto - $totalCorrecto) > 0.01) {
                $tieneTotalIncorrecto = true;

                break;
            }
        }

        if (! $tieneTotalIncorrecto) {
            return $texto;
        }

        Log::warning('NormalizadorRespuestaAgente: corrigiendo total de 1 unidad cuando el pedido tiene más', [
            'sale_id' => $sale->id,
            'quantity' => $quantity,
            'total_correcto' => $totalCorrecto,
            'total_incorrecto_detectado' => $totalUnaUnidad,
        ]);

        $variantesIncorrectas = $this->variantesMonto($totalUnaUnidad, $simbolo);
        $variantesCorrectas = $this->variantesMonto($totalCorrecto, $simbolo);

        foreach ($variantesIncorrectas as $incorrecto) {
            foreach ($variantesCorrectas as $correcto) {
                $texto = str_replace($incorrecto, $correcto, $texto);
            }
        }

        return $texto;
    }

    /**
     * @return list<float>
     */
    private function extraerMontos(string $texto): array
    {
        if (preg_match_all('/(?:S\/|\$|€)\s*(\d+(?:[.,]\d{1,2})?)/u', $texto, $matches) !== 1) {
            return [];
        }

        $montos = [];
        foreach ($matches[1] as $raw) {
            $normalizado = (float) str_replace(',', '.', (string) $raw);
            $montos[] = $normalizado;
        }

        return $montos;
    }

    /**
     * @return list<string>
     */
    private function variantesMonto(float $monto, string $simbolo = 'S/'): array
    {
        $entero = number_format($monto, 0, '.', '');
        $dosDecimales = number_format($monto, 2, '.', '');
        $dosDecimalesComa = number_format($monto, 2, ',', '');

        return array_values(array_unique([
            $simbolo.$entero,
            $simbolo.' '.$entero,
            $simbolo.$dosDecimales,
            $simbolo.' '.$dosDecimales,
            $simbolo.$dosDecimalesComa,
            $simbolo.' '.$dosDecimalesComa,
        ]));
    }
}
