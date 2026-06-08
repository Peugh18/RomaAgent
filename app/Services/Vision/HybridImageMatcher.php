<?php

namespace App\Services\Vision;

use Illuminate\Support\Collection;

/**
 * Matcher híbrido que combina análisis textual + similitud visual + contexto
 * Sistema de reconocimiento inteligente con aprendizaje adaptativo
 */
class HybridImageMatcher
{
    private const PESOS = [
        'visual' => 0.45,      // Similitud vectorial
        'textual' => 0.35,     // Matching tradicional
        'contexto' => 0.20,    // Feedback usuario, popularidad
    ];

    private const UMBRALES_ADAPTATIVOS = [
        'vestido' => 0.75,
        'blusa' => 0.70,
        'pantalon' => 0.72,
        'accesorio' => 0.65,
        'default' => 0.70,
    ];

    public function __construct(
        private ProductEmbeddingService $embeddingService,
        private VectorSearchService $vectorSearch,
        private CatalogoImageMatcher $textualMatcher,
    ) {}

    /**
     * Matching híbrido principal
     *
     * @param  array<string, mixed>  $inboundProfile
     * @param  string  $imageUrl  URL de imagen del cliente
     * @return array Resultado enriquecido con múltiples estrategias
     */
    public function matchHibrido(array $inboundProfile, ?string $captionCliente = null): array
    {
        // 1. Análisis textual (perfiles + keywords)
        $resultadoTextual = $this->textualMatcher->match($inboundProfile);

        // 2. Similitud vectorial (si hay embeddings en catálogo)
        $similitudVisual = $this->vectorSearch->buscarPorAnalisisCliente(
            $inboundProfile,
            $captionCliente,
            $this->embeddingService,
        );

        // 3. Combinar resultados con pesos inteligentes
        $resultadoCombinado = $this->combinarResultados(
            $resultadoTextual,
            $similitudVisual,
            $inboundProfile
        );

        // 4. Aplicar umbral adaptativo
        $umbral = $this->obtenerUmbralAdaptativo($inboundProfile);
        $resultadoFinal = $this->aplicarUmbral($resultadoCombinado, $umbral);

        // 5. Enriquecer con metadata adicional
        return $this->enriquecerResultado($resultadoFinal, $inboundProfile, $captionCliente);
    }

    /**
     * Combina resultados textuales y visuales usando pesos
     */
    private function combinarResultados(array $resultadoTextual, Collection $similitudVisual, array $inboundProfile): array
    {
        $matchesCombinados = [];

        // Procesar matches textuales
        foreach ($resultadoTextual['matches'] as $match) {
            $variantId = $match['variant_id'];
            $scoreTextual = $match['score'];

            // Buscar similitud visual si existe
            $visualMatch = $similitudVisual->first(
                fn (array $row) => $row['variant']->id === $variantId
            );
            $scoreVisual = $visualMatch ? $visualMatch['similarity'] : 0.0;

            // Calcular score combinado
            $scoreCombinado = $this->calcularScoreCombinado(
                $scoreTextual,
                $scoreVisual,
                $inboundProfile
            );

            $matchesCombinados[] = [
                'product_id' => $match['product_id'],
                'variant_id' => $variantId,
                'product_name' => $match['product_name'],
                'color' => $match['color'],
                'score' => $scoreCombinado,
                'score_textual' => $scoreTextual,
                'score_visual' => $scoreVisual,
                'score_contexto' => 0.0, // TODO: Implementar contexto
                'razones' => array_merge($match['razones'], $this->generarRazonesVisuales($scoreVisual)),
            ];
        }

        // Agregar matches puramente visuales (no encontrados textualmente)
        foreach ($similitudVisual as $visual) {
            $variantId = $visual['variant']->id;

            // Saltar si ya fue procesado
            if (collect($matchesCombinados)->firstWhere('variant_id', $variantId)) {
                continue;
            }

            $scoreCombinado = $this->calcularScoreCombinado(
                0.0, // Sin score textual
                $visual['similarity'],
                $inboundProfile
            );

            $matchesCombinados[] = [
                'product_id' => $visual['variant']->product_id,
                'variant_id' => $variantId,
                'product_name' => $visual['variant']->product->name,
                'color' => $visual['variant']->color,
                'score' => $scoreCombinado,
                'score_textual' => 0.0,
                'score_visual' => $visual['similarity'],
                'score_contexto' => 0.0,
                'razones' => $this->generarRazonesVisuales($visual['similarity']),
            ];
        }

        // Ordenar por score combinado
        usort($matchesCombinados, fn ($a, $b) => $b['score'] <=> $a['score']);

        return [
            'matches' => array_slice($matchesCombinados, 0, 5),
            'mejor_match' => $matchesCombinados[0] ?? null,
            'confianza_final' => ($matchesCombinados[0]['score'] ?? 0.0),
            'estrategia' => 'hibrida',
            'total_textual' => count($resultadoTextual['matches']),
            'total_visual' => $similitudVisual->count(),
        ];
    }

    /**
     * Calcula score combinado usando pesos y contexto
     */
    private function calcularScoreCombinado(float $scoreTextual, float $scoreVisual, array $inboundProfile): float
    {
        $pesos = self::PESOS;

        // Ajustar pesos según tipo de prenda
        $tipoPrenda = $inboundProfile['tipo_prenda'] ?? 'default';
        if (in_array($tipoPrenda, ['vestido', 'blusa'])) {
            $pesos['visual'] = 0.50; // Dar más peso a visual en prendas principales
            $pesos['textual'] = 0.30;
        }

        $pesoActivo = $pesos['textual'] + $pesos['visual'];

        if ($pesoActivo <= 0.0) {
            return 0.0;
        }

        $scoreCombinado =
            ($scoreTextual * $pesos['textual']) +
            ($scoreVisual * $pesos['visual']);

        return min(1.0, $scoreCombinado / $pesoActivo);
    }

    /**
     * Obtiene umbral adaptativo según tipo de producto
     */
    private function obtenerUmbralAdaptativo(array $inboundProfile): float
    {
        $tipoPrenda = $inboundProfile['tipo_prenda'] ?? 'default';

        return self::UMBRALES_ADAPTATIVOS[$tipoPrenda] ?? self::UMBRALES_ADAPTATIVOS['default'];
    }

    /**
     * Aplica umbral y filtra resultados
     */
    private function aplicarUmbral(array $resultado, float $umbral): array
    {
        $matchesFiltrados = array_filter($resultado['matches'], fn ($match) => $match['score'] >= $umbral);

        $resultado['matches'] = array_values($matchesFiltrados);
        $resultado['mejor_match'] = $resultado['matches'][0] ?? null;
        $resultado['confianza_final'] = $resultado['mejor_match']['score'] ?? 0.0;
        $resultado['umbral_aplicado'] = $umbral;

        return $resultado;
    }

    /**
     * Enriquece resultado con metadata adicional
     */
    private function enriquecerResultado(array $resultado, array $inboundProfile, ?string $captionCliente): array
    {
        // Agregar nivel de confianza
        $confianza = $resultado['confianza_final'];
        $resultado['nivel_confianza'] = $this->determinarNivelConfianza($confianza);
        $resultado['nivel'] = $resultado['nivel_confianza'];

        // Agregar recomendaciones para el agente
        $resultado['recomendaciones'] = $this->generarRecomendaciones($resultado, $inboundProfile);

        // Agregar metadata para debugging
        $resultado['debug'] = [
            'inbound_tipo' => $inboundProfile['tipo_prenda'] ?? 'desconocido',
            'inbound_colores' => $inboundProfile['colores_dominantes'] ?? [],
            'caption_cliente' => $captionCliente,
            'estrategia_usada' => $resultado['estrategia'],
            'matches_textuales' => $resultado['total_textual'],
            'matches_visuales' => $resultado['total_visual'],
        ];

        return $resultado;
    }

    /**
     * Determina nivel de confianza basado en score
     */
    private function determinarNivelConfianza(float $score): string
    {
        if ($score >= 0.85) {
            return 'muy_alta';
        }
        if ($score >= 0.75) {
            return 'alta';
        }
        if ($score >= 0.65) {
            return 'media';
        }

        return 'baja';
    }

    /**
     * Genera razones basadas en similitud visual
     */
    private function generarRazonesVisuales(float $scoreVisual): array
    {
        $razones = [];
        if ($scoreVisual >= 0.8) {
            $razones[] = 'similitud_visual_muy_alta';
        } elseif ($scoreVisual >= 0.7) {
            $razones[] = 'similitud_visual_alta';
        } elseif ($scoreVisual >= 0.6) {
            $razones[] = 'similitud_visual_media';
        }

        return $razones;
    }

    /**
     * Genera recomendaciones para el agente IA
     */
    private function generarRecomendaciones(array $resultado, array $inboundProfile): array
    {
        $recomendaciones = [];
        $nivel = $resultado['nivel_confianza'];

        switch ($nivel) {
            case 'muy_alta':
                $recomendaciones[] = 'confirmar_producto_directamente';
                $recomendaciones[] = 'ofrecer_variants_colores_similares';
                break;
            case 'alta':
                $recomendaciones[] = 'confirmar_gentimente_producto';
                break;
            case 'media':
                $recomendaciones[] = 'preguntar_si_es_producto_mostrado';
                $recomendaciones[] = 'ofrecer_alternativas_similares';
                break;
            case 'baja':
                $recomendaciones[] = 'usar_busqueda_textual_tradicional';
                $recomendaciones[] = 'no_forzar_producto_visual';
                break;
        }

        return $recomendaciones;
    }

    /**
     * Formatea resultado para el agente IA (compatible con sistema existente)
     */
    public function formatearParaAgente(array $resultado, ?string $captionCliente = null): string
    {
        $lineas = ['[La clienta envió una imagen de producto - Análisis Híbrido].'];

        if ($captionCliente && trim($captionCliente) !== '') {
            $lineas[] = 'Caption clienta: "'.trim($captionCliente).'".';
        }

        $mejor = $resultado['mejor_match'] ?? null;
        if (! $mejor) {
            $lineas[] = 'Match híbrido: sin coincidencia clara. Usa buscar_productos con descripción visual.';

            return implode(' ', $lineas);
        }

        $pct = (int) round($mejor['score'] * 100);
        $color = $mejor['color'] ?? '?';
        $estrategia = $resultado['estrategia'] ?? 'desconocida';

        $lineas[] = sprintf(
            'Match híbrido (%s): %s / %s (%d%% confianza, nivel %s).',
            $estrategia,
            $mejor['product_name'],
            $color,
            $pct,
            $resultado['nivel_confianza']
        );

        // Agregar breakdown de scores si es útil
        if ($mejor['score_textual'] > 0 && $mejor['score_visual'] > 0) {
            $lineas[] = sprintf(
                '(Textual: %d%%, Visual: %d%%)',
                (int) round($mejor['score_textual'] * 100),
                (int) round($mejor['score_visual'] * 100)
            );
        }

        // Alternativas
        $alternativas = array_slice($resultado['matches'], 1, 2);
        if (! empty($alternativas)) {
            $altTexto = collect($alternativas)
                ->map(fn ($m) => ($m['product_name'] ?? '?').'/'.($m['color'] ?? '?').' '.(int) round($m['score'] * 100).'%')
                ->implode(', ');
            $lineas[] = 'Alternativas: '.$altTexto.'.';
        }

        // Recomendaciones específicas
        $recomendaciones = $resultado['recomendaciones'] ?? [];
        if (! empty($recomendaciones)) {
            $lineas[] = 'Acción sugerida: '.implode(', ', $recomendaciones).'.';
        }

        return implode(' ', $lineas);
    }
}
