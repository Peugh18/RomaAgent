<?php

namespace App\Services\Vision;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Sistema de aprendizaje continuo para mejorar reconocimiento visual
 * Aprende de confirmaciones y correcciones del usuario
 */
class VisionLearningService
{
    /**
     * Registra un match automático pendiente de revisión (desde WhatsApp).
     *
     * @param  array<string, mixed>  $contextoAnalisis
     */
    public function registrarMatchDetectado(int $variantId, array $contextoAnalisis): int
    {
        $id = DB::table('vision_learning_feedback')->insertGetId([
            'variant_id' => $variantId,
            'tipo_feedback' => 'pendiente',
            'contexto_analisis' => json_encode($contextoAnalisis, JSON_UNESCAPED_UNICODE),
            'peso_aprendizaje' => $this->calcularPesoAprendizaje($contextoAnalisis),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Log::info('Match de visión registrado para revisión', [
            'feedback_id' => $id,
            'variant_id' => $variantId,
            'confianza' => $contextoAnalisis['confianza_analisis'] ?? null,
        ]);

        return $id;
    }

    /**
     * Registra feedback positivo (match acertado)
     */
    public function registrarFeedbackPositivo(int $variantId, array $contextoAnalisis): void
    {
        DB::table('vision_learning_feedback')->insert([
            'variant_id' => $variantId,
            'tipo_feedback' => 'positivo',
            'contexto_analisis' => json_encode($contextoAnalisis),
            'peso_aprendizaje' => $this->calcularPesoAprendizaje($contextoAnalisis),
            'created_at' => now(),
        ]);

        // Actualizar popularidad del producto
        $this->incrementarPopularidad($variantId);

        Log::info('Feedback positivo registrado', [
            'variant_id' => $variantId,
            'contexto' => $contextoAnalisis,
        ]);
    }

    /**
     * Registra feedback negativo (match incorrecto)
     */
    public function registrarFeedbackNegativo(int $variantId, array $contextoAnalisis, ?int $variantIdCorrecto = null): void
    {
        DB::table('vision_learning_feedback')->insert([
            'variant_id' => $variantId,
            'variant_id_correcto' => $variantIdCorrecto,
            'tipo_feedback' => 'negativo',
            'contexto_analisis' => json_encode($contextoAnalisis),
            'peso_aprendizaje' => $this->calcularPesoAprendizaje($contextoAnalisis),
            'created_at' => now(),
        ]);

        // Disminuir confianza temporal de este producto
        $this->ajustarConfianzaProducto($variantId, -0.1);

        Log::info('Feedback negativo registrado', [
            'variant_id' => $variantId,
            'correcto_id' => $variantIdCorrecto,
            'contexto' => $contextoAnalisis,
        ]);
    }

    /**
     * Calcula peso de aprendizaje basado en contexto
     */
    private function calcularPesoAprendizaje(array $contexto): float
    {
        $peso = 1.0;

        // Mayor peso si el usuario confirmó explícitamente
        if (($contexto['confirmacion_usuario'] ?? false) === true) {
            $peso += 0.5;
        }

        // Mayor peso si hubo alta confianza en el análisis
        $confianza = $contexto['confianza_analisis'] ?? 0.5;
        $peso += $confianza * 0.3;

        // Mayor peso si es tipo de producto común
        $tipoPrenda = $contexto['tipo_prenda'] ?? '';
        if (in_array($tipoPrenda, ['vestido', 'blusa'])) {
            $peso += 0.2;
        }

        return min(2.0, $peso);
    }

    /**
     * Incrementa popularidad de una variante
     */
    private function incrementarPopularidad(int $variantId): void
    {
        DB::table('product_variants')
            ->where('id', $variantId)
            ->increment('vision_popularity', 1);
    }

    /**
     * Ajusta confianza de un producto
     */
    private function ajustarConfianzaProducto(int $variantId, float $ajuste): void
    {
        $actual = (float) (DB::table('product_variants')
            ->where('id', $variantId)
            ->value('vision_confidence') ?? 0.7);

        $nuevo = max(0.1, min(1.0, $actual + $ajuste));

        DB::table('product_variants')
            ->where('id', $variantId)
            ->update(['vision_confidence' => $nuevo]);
    }

    /**
     * Obtiene productos populares para boosting en resultados
     */
    public function obtenerProductosPopulares(int $limit = 10): array
    {
        return DB::table('product_variants as pv')
            ->join('products as p', 'pv.product_id', '=', 'p.id')
            ->where('p.status', 'disponible')
            ->where('pv.vision_popularity', '>', 0)
            ->orderByDesc('pv.vision_popularity')
            ->limit($limit)
            ->pluck('pv.id')
            ->toArray();
    }

    /**
     * Ajusta umbrales basados en histórico de feedback
     */
    public function obtenerUmbralAjustado(string $tipoPrenda, float $umbralBase): float
    {
        // Calcular tasa de aciertos reciente para este tipo de prenda
        $aciertosRecientes = DB::table('vision_learning_feedback as vf')
            ->join('product_variants as pv', 'vf.variant_id', '=', 'pv.id')
            ->join('products as p', 'pv.product_id', '=', 'p.id')
            ->where('vf.tipo_feedback', 'positivo')
            ->where('vf.created_at', '>', now()->subDays(7))
            ->whereJsonContains('vf.contexto_analisis->tipo_prenda', $tipoPrenda)
            ->count();

        $totalesRecientes = DB::table('vision_learning_feedback as vf')
            ->join('product_variants as pv', 'vf.variant_id', '=', 'pv.id')
            ->join('products as p', 'pv.product_id', '=', 'p.id')
            ->where('vf.created_at', '>', now()->subDays(7))
            ->whereJsonContains('vf.contexto_analisis->tipo_prenda', $tipoPrenda)
            ->count();

        if ($totalesRecientes < 5) {
            return $umbralBase; // Datos insuficientes
        }

        $tasaAciertos = $aciertosRecientes / $totalesRecientes;

        // Ajustar umbral según tasa de aciertos
        if ($tasaAciertos > 0.85) {
            return $umbralBase - 0.05; // Bajar umbral si funciona bien
        } elseif ($tasaAciertos < 0.65) {
            return $umbralBase + 0.05; // Subir umbral si hay muchos errores
        }

        return $umbralBase;
    }

    /**
     * Genera reporte de aprendizaje
     */
    public function generarReporteAprendizaje(): array
    {
        $ultimos7Dias = now()->subDays(7);

        $feedbackPositivo = DB::table('vision_learning_feedback')
            ->where('tipo_feedback', 'positivo')
            ->where('created_at', '>', $ultimos7Dias)
            ->count();

        $feedbackNegativo = DB::table('vision_learning_feedback')
            ->where('tipo_feedback', 'negativo')
            ->where('created_at', '>', $ultimos7Dias)
            ->count();

        $feedbackPendiente = DB::table('vision_learning_feedback')
            ->where('tipo_feedback', 'pendiente')
            ->where('created_at', '>', $ultimos7Dias)
            ->count();

        $productosTop = DB::table('product_variants as pv')
            ->join('products as p', 'pv.product_id', '=', 'p.id')
            ->where('pv.vision_popularity', '>', 0)
            ->orderByDesc('pv.vision_popularity')
            ->limit(5)
            ->get(['pv.id', 'p.name', 'pv.color', 'pv.vision_popularity']);

        $tiposPrendaAciertos = $this->agruparRendimientoPorTipoPrenda($ultimos7Dias);

        return [
            'periodo' => 'últimos 7 días',
            'feedback_positivo' => $feedbackPositivo,
            'feedback_negativo' => $feedbackNegativo,
            'feedback_pendiente' => $feedbackPendiente,
            'tasa_acierto' => $feedbackPositivo + $feedbackNegativo > 0
                ? round($feedbackPositivo / ($feedbackPositivo + $feedbackNegativo) * 100, 2)
                : 0,
            'productos_populares' => $productosTop->toArray(),
            'tipos_prenda_rendimiento' => $tiposPrendaAciertos,
        ];
    }

    /**
     * @return list<array{tipo_prenda: string, total: int, aciertos: int}>
     */
    private function agruparRendimientoPorTipoPrenda(\DateTimeInterface $desde): array
    {
        $filas = DB::table('vision_learning_feedback')
            ->where('created_at', '>', $desde)
            ->whereNotNull('contexto_analisis')
            ->whereIn('tipo_feedback', ['positivo', 'negativo'])
            ->get(['tipo_feedback', 'contexto_analisis']);

        $agrupado = [];

        foreach ($filas as $fila) {
            $contexto = json_decode((string) $fila->contexto_analisis, true);
            $tipoPrenda = is_array($contexto) && ! empty($contexto['tipo_prenda'])
                ? (string) $contexto['tipo_prenda']
                : 'desconocido';

            if (! isset($agrupado[$tipoPrenda])) {
                $agrupado[$tipoPrenda] = [
                    'tipo_prenda' => $tipoPrenda,
                    'total' => 0,
                    'aciertos' => 0,
                ];
            }

            $agrupado[$tipoPrenda]['total']++;
            if ($fila->tipo_feedback === 'positivo') {
                $agrupado[$tipoPrenda]['aciertos']++;
            }
        }

        return array_values($agrupado);
    }

    /**
     * Limpia datos antiguos de aprendizaje
     */
    public function limpiarDatosAntiguos(int $dias = 90): void
    {
        $eliminados = DB::table('vision_learning_feedback')
            ->where('created_at', '<', now()->subDays($dias))
            ->delete();

        Log::info('Datos antiguos de aprendizaje eliminados', [
            'dias' => $dias,
            'registros_eliminados' => $eliminados,
        ]);
    }
}
