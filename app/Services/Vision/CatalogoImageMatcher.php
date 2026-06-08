<?php

namespace App\Services\Vision;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Vision\PerfilVisionFallback;

class CatalogoImageMatcher
{
    private const UMBRAL_ALTA = 0.85;

    private const UMBRAL_MEDIA = 0.50;

    /**
     * @param  array<string, mixed>  $inboundProfile
     * @return array{
     *   matches: list<array{product_id: int, variant_id: int|null, product_name: string, color: string|null, score: float, razones: list<string>}>,
     *   mejor_match: array{product_id: int, variant_id: int|null, product_name: string, color: string|null, score: float, razones: list<string>}|null,
     *   confianza_final: float,
     *   nivel: string
     * }
     */
    public function match(array $inboundProfile): array
    {
        if (($inboundProfile['tipo'] ?? '') === 'comprobante' || ($inboundProfile['es_comprobante'] ?? false) === true) {
            return $this->resultadoVacio();
        }

        $productos = Product::query()
            ->where('status', Product::ESTADO_DISPONIBLE)
            ->with('variants')
            ->get();

        if ($productos->isEmpty()) {
            return $this->resultadoVacio();
        }

        $scoredProducts = $productos
            ->map(fn (Product $p): array => [
                'product' => $p,
                'score' => $this->scoreProducto($p, $inboundProfile),
            ])
            ->sortByDesc('score')
            ->values();

        $matches = [];
        $topProducts = $scoredProducts->take(3);

        foreach ($topProducts as $entry) {
            /** @var Product $product */
            $product = $entry['product'];
            $productScore = (float) $entry['score'];

            if ($productScore < 0.15) {
                continue;
            }

            $variants = $product->variants;
            if ($variants->isEmpty()) {
                $matches[] = $this->buildMatch($product, null, $productScore, ['producto']);

                continue;
            }

            foreach ($variants as $variant) {
                $colorScore = $this->scoreColor($variant, $inboundProfile);
                $finalScore = ($productScore * 0.55) + ($colorScore * 0.45);
                $razones = array_values(array_filter([
                    $productScore >= 0.3 ? 'producto' : null,
                    $colorScore >= 0.3 ? 'color' : null,
                ]));

                $matches[] = $this->buildMatch($product, $variant, round($finalScore, 4), $razones);
            }
        }

        usort($matches, fn (array $a, array $b): int => $b['score'] <=> $a['score']);
        $matches = array_slice($matches, 0, 5);

        $mejor = $matches[0] ?? null;
        $confianza = $mejor !== null ? (float) $mejor['score'] : 0.0;

        return [
            'matches' => $matches,
            'mejor_match' => $mejor,
            'confianza_final' => $confianza,
            'nivel' => $this->nivelConfianza($confianza),
        ];
    }

    /**
     * @param  array<string, mixed>  $inboundProfile
     */
    private function scoreProducto(Product $product, array $inboundProfile): float
    {
        $score = 0.0;
        $profile = is_array($product->vision_profile) ? $product->vision_profile : [];

        $tipoInbound = mb_strtolower((string) ($inboundProfile['tipo_prenda'] ?? ''));
        $tipoCatalog = mb_strtolower((string) ($profile['tipo_prenda'] ?? ''));
        if ($tipoInbound !== '' && $tipoCatalog !== '' && ($tipoInbound === $tipoCatalog || str_contains($tipoCatalog, $tipoInbound))) {
            $score += 0.35;
        }

        $materialInbound = mb_strtolower((string) ($inboundProfile['material_aparente'] ?? ''));
        $materialCatalog = mb_strtolower((string) ($profile['material_aparente'] ?? ''));
        if ($materialInbound !== '' && $materialCatalog !== '' && str_contains($materialCatalog, $materialInbound)) {
            $score += 0.2;
        }

        $keywordsInbound = $this->normalizarLista($inboundProfile['descripcion_prenda'] ?? $inboundProfile['texto_visible'] ?? '');
        $keywordsProfile = $this->normalizarLista($profile['keywords'] ?? []);
        $keywordsTags = $this->normalizarLista($product->tags_ia ?? []);
        $keywordsName = $this->normalizarLista($product->name);

        $haystack = implode(' ', array_merge($keywordsProfile, $keywordsTags, $keywordsName));
        $keywordHits = 0;
        foreach ($keywordsInbound as $word) {
            if (strlen($word) >= 3 && str_contains($haystack, $word)) {
                $keywordHits++;
            }
        }
        if ($keywordHits > 0) {
            $score += min(0.35, $keywordHits * 0.12);
        }

        $caption = mb_strtolower((string) ($inboundProfile['caption_cliente'] ?? ''));
        $nameLower = mb_strtolower($product->name);
        if ($caption !== '' && str_contains($caption, $nameLower)) {
            $score += 0.25;
        }

        $corpus = $this->corpusTextoInbound($inboundProfile);
        if ($nameLower !== '' && $corpus !== '' && str_contains($corpus, $nameLower)) {
            $score += 0.45;
        }

        return min(1.0, $score);
    }

    /**
     * @param  array<string, mixed>  $inboundProfile
     */
    private function scoreColor(ProductVariant $variant, array $inboundProfile): float
    {
        $coloresInbound = $this->normalizarLista($inboundProfile['colores_dominantes'] ?? []);
        if ($coloresInbound === []) {
            $coloresInbound = $this->normalizarLista((string) ($inboundProfile['color_dominante'] ?? ''));
        }

        $colorVariant = mb_strtolower(trim($variant->color));
        $corpus = $this->corpusTextoInbound($inboundProfile);

        if ($colorVariant !== '' && $corpus !== '' && str_contains($corpus, $colorVariant)) {
            return 0.95;
        }

        foreach (PerfilVisionFallback::aliasesParaColor($variant->color) as $alias) {
            if ($alias !== '' && $corpus !== '' && str_contains($corpus, $alias)) {
                return 0.9;
            }
        }

        $profile = is_array($variant->color_profile) ? $variant->color_profile : [];
        $aliases = array_map(
            mb_strtolower(...),
            $this->normalizarLista($profile['aliases'] ?? [])
        );
        $dominantes = array_map(
            mb_strtolower(...),
            $this->normalizarLista($profile['colores_dominantes'] ?? [])
        );

        $candidatos = array_unique(array_merge([$colorVariant], $aliases, $dominantes));

        foreach ($coloresInbound as $inboundColor) {
            foreach ($candidatos as $candidato) {
                if ($candidato === '' || $inboundColor === '') {
                    continue;
                }
                if ($candidato === $inboundColor || str_contains($candidato, $inboundColor) || str_contains($inboundColor, $candidato)) {
                    return 1.0;
                }
            }
        }

        if ($coloresInbound === []) {
            return 0.35;
        }

        return 0.1;
    }

    /**
     * @param  list<string>  $razones
     * @return array{product_id: int, variant_id: int|null, product_name: string, color: string|null, score: float, razones: list<string>}
     */
    private function buildMatch(Product $product, ?ProductVariant $variant, float $score, array $razones): array
    {
        return [
            'product_id' => $product->id,
            'variant_id' => $variant?->id,
            'product_name' => $product->name,
            'color' => $variant?->color,
            'score' => round($score, 4),
            'razones' => $razones,
        ];
    }

    private function nivelConfianza(float $score): string
    {
        if ($score >= self::UMBRAL_ALTA) {
            return 'alta';
        }

        if ($score >= self::UMBRAL_MEDIA) {
            return 'media';
        }

        return 'baja';
    }

    /**
     * @return array{matches: list<empty>, mejor_match: null, confianza_final: float, nivel: string}
     */
    private function resultadoVacio(): array
    {
        return [
            'matches' => [],
            'mejor_match' => null,
            'confianza_final' => 0.0,
            'nivel' => 'baja',
        ];
    }

    /**
     * @return list<string>
     */
    private function normalizarLista(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/[\s,;|]+/u', mb_strtolower(trim($value))) ?: [];
        }

        if (! is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $item) {
            if (is_string($item) && trim($item) !== '') {
                $out[] = mb_strtolower(trim($item));
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @param  array{mejor_match: array<string, mixed>|null, confianza_final: float, nivel: string, matches: list<array<string, mixed>>}  $resultado
     */
    public function formatearParaAgente(array $resultado, ?string $captionCliente = null): string
    {
        $lineas = ['[La clienta envió una imagen de producto].'];

        if ($captionCliente !== null && trim($captionCliente) !== '') {
            $lineas[] = 'Caption clienta: "'.trim($captionCliente).'".';
        }

        $mejor = $resultado['mejor_match'] ?? null;
        if ($mejor === null) {
            $lineas[] = 'Match catálogo: sin coincidencia clara. Usa buscar_productos con lo que describa la imagen.';

            return implode(' ', $lineas);
        }

        $pct = (int) round(((float) $resultado['confianza_final']) * 100);
        $color = is_string($mejor['color'] ?? null) ? $mejor['color'] : '?';
        $lineas[] = sprintf(
            'Match catálogo: %s / %s (%d%% confianza, nivel %s).',
            $mejor['product_name'],
            $color,
            $pct,
            $resultado['nivel']
        );

        $alternativas = array_slice($resultado['matches'] ?? [], 1, 2);
        if ($alternativas !== []) {
            $altTexto = collect($alternativas)
                ->map(fn (array $m): string => ($m['product_name'] ?? '?').'/'.($m['color'] ?? '?').' '.(int) round($m['score'] * 100).'%')
                ->implode(', ');
            $lineas[] = 'Alternativas: '.$altTexto.'.';
        }

        $nivel = $resultado['nivel'];
        if ($nivel === 'alta') {
            $lineas[] = 'Confirma el modelo/color, llama verificar_stock y actualizar_pedido; ofrece enviar_foto_producto si tiene foto en catálogo.';
        } elseif ($nivel === 'media') {
            $lineas[] = 'Pregunta amablemente si es ese modelo/color antes de confirmar.';
        } else {
            $lineas[] = 'Usa buscar_productos; no inventes producto.';
        }

        return implode(' ', $lineas);
    }

    /**
     * @param  array<string, mixed>  $inboundProfile
     */
    private function corpusTextoInbound(array $inboundProfile): string
    {
        $partes = [
            (string) ($inboundProfile['caption_cliente'] ?? ''),
            (string) ($inboundProfile['descripcion_prenda'] ?? ''),
            (string) ($inboundProfile['texto_visible'] ?? ''),
            (string) ($inboundProfile['color_dominante'] ?? ''),
        ];

        $colores = $inboundProfile['colores_dominantes'] ?? [];
        if (is_array($colores)) {
            $partes = array_merge($partes, array_map(strval(...), $colores));
        }

        return mb_strtolower(trim(implode(' ', array_filter($partes))));
    }
}
