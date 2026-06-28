<?php

namespace App\Services\Media;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VisualCorrection;
use App\Services\ConfiguracionAgente;
use App\Services\ServicioMediaProducto;
use App\Services\Vision\GarmentVisionService;
use App\Services\Vision\ProductEmbeddingService;
use Illuminate\Support\Facades\Log;

/**
 * Servicio de análisis de imágenes usando Gemini API.
 *
 * @extends BaseGeminiService
 */
class ImageAnalyzer extends BaseGeminiService
{
    public function __construct(
        ConfiguracionAgente $configuracion,
        private CargadorBytesMedia $cargador,
        private ProductEmbeddingService $embeddingService,
        private ServicioMediaProducto $mediaProducto,
        private GarmentVisionService $visionService
    ) {
        parent::__construct($configuracion);
    }

    /**
     * @param  array<string, mixed>  $contexto
     * @return array{
     *   caption: string,
     *   inbound_profile: array<string, mixed>
     * }|null
     */
    public function analyzeUrl(string $imageUrl, array $contexto = []): ?array
    {
        $apiKey = $this->obtenerApiKey();
        if ($apiKey === null) {
            return null;
        }

        $media = $this->cargador->desdeUrl($imageUrl);
        if ($media === null) {
            Log::warning('ImageAnalyzer: no se pudo cargar la imagen', ['url' => $imageUrl]);

            return null;
        }

        $captionCliente = trim((string) ($contexto['caption_cliente'] ?? ''));

        // --- PASO A: BÚSQUEDA POR HASH EXACTO DE CORRECCIÓN VISUAL ---
        $imageHash = md5($media['bytes']);
        $correction = VisualCorrection::where('image_hash', $imageHash)->first();
        if ($correction) {
            $product = $correction->product;
            if ($product && $product->status === Product::ESTADO_DISPONIBLE) {
                $firstVariant = $product->variants()->first();
                Log::info('ImageAnalyzer: Coincidencia por HASH EXACTO de corrección visual', [
                    'hash' => $imageHash,
                    'producto_id' => $product->id,
                ]);

                return [
                    'caption' => 'Producto reconocido por corrección visual exacta (hash)',
                    'inbound_profile' => [
                        'encontrado' => true,
                        'matches' => [[
                            'id_producto' => $product->id,
                            'nombre_vestido' => $product->name,
                            'color' => $firstVariant?->color ?? 'Desconocido',
                            'similitud' => 1.0,
                            'image_url' => $firstVariant ? $this->mediaProducto->resolveAbsolutePublicUrl($firstVariant) : null,
                            'es_mismo_color' => true,
                        ]],
                        'tipo_mensaje' => 'producto',
                        'caption_cliente' => $captionCliente,
                    ],
                ];
            }
        }

        // ANÁLISIS UNIFICADO: Una sola llamada a Gemini detecta tanto comprobantes como prendas.
        // Esto ahorra tokens, tiempo y costo (antes se hacían 2 llamadas por foto).
        $analysis = $this->visionService->analyze($imageUrl);

        // Detectar comprobante (integrado en el prompt universal V4)
        if ($analysis !== null && $analysis->esComprobante) {
            Log::info('ImageAnalyzer: Comprobante detectado.');

            return [
                'caption' => 'Comprobante detectado',
                'inbound_profile' => [
                    'tipo_mensaje' => 'comprobante',
                    'es_comprobante' => true,
                    'encontrado' => false,
                    'caption_cliente' => $captionCliente,
                ],
            ];
        }

        if ($analysis === null || ! $analysis->esPrenda || empty($analysis->descripcionVectorial)) {
            Log::info('ImageAnalyzer: No se detectó una prenda clara en la imagen o falló la descripción. Devolviendo no encontrado.');

            return [
                'caption' => 'Producto no reconocido con certeza',
                'inbound_profile' => [
                    'encontrado' => false,
                    'matches' => [],
                    'tipo_mensaje' => 'producto',
                    'caption_cliente' => $captionCliente,
                ],
            ];
        }

        $descripcion = $analysis->descripcionVectorial;
        $colorExtraido = $analysis->colorPrincipal;

        Log::info('ImageAnalyzer: Descripción extraída de la imagen (Motor Unificado)', ['desc' => $descripcion]);

        // 3. OBTENER EMBEDDING DEL TEXTO DESCRIPTIVO
        $embedding = $this->embeddingService->generarEmbeddingTexto($descripcion);
        if ($embedding === null) {
            Log::warning('ImageAnalyzer: No se pudo generar el embedding del texto. Devolviendo no encontrado.');

            return [
                'caption' => 'Producto no reconocido con certeza',
                'inbound_profile' => [
                    'encontrado' => false,
                    'matches' => [],
                    'tipo_mensaje' => 'producto',
                    'caption_cliente' => $captionCliente,
                ],
            ];
        }

        // --- PASO B: BÚSQUEDA POR SIMILITUD DE EMBEDDING EN CORRECCIONES VISUALES ---
        $correcciones = VisualCorrection::whereNotNull('image_embedding')->get();
        $mejorCorreccion = null;
        $mejorSimilitud = 0.0;

        foreach ($correcciones as $corr) {
            if (is_array($corr->image_embedding)) {
                $sim = $this->cosineSimilarity($embedding, $corr->image_embedding);
                if ($sim > $mejorSimilitud) {
                    $mejorSimilitud = $sim;
                    $mejorCorreccion = $corr;
                }
            }
        }

        // Umbral alto (95%) para estar seguros de que es la misma foto o prenda corregida
        if ($mejorCorreccion && $mejorSimilitud >= 0.95) {
            $product = $mejorCorreccion->product;
            if ($product && $product->status === Product::ESTADO_DISPONIBLE) {
                $firstVariant = $product->variants()->first();
                Log::info('ImageAnalyzer: Coincidencia por SIMILITUD de corrección visual', [
                    'producto_id' => $product->id,
                    'similitud' => $mejorSimilitud,
                ]);

                return [
                    'caption' => 'Producto reconocido por corrección visual similar',
                    'inbound_profile' => [
                        'encontrado' => true,
                        'matches' => [[
                            'id_producto' => $product->id,
                            'nombre_vestido' => $product->name,
                            'color' => $firstVariant?->color ?? 'Desconocido',
                            'similitud' => min($mejorSimilitud + 0.05, 1.0),
                            'image_url' => $firstVariant ? $this->mediaProducto->resolveAbsolutePublicUrl($firstVariant) : null,
                            'es_mismo_color' => true,
                        ]],
                        'tipo_mensaje' => 'producto',
                        'caption_cliente' => $captionCliente,
                        'huella_forma' => $analysis->huellaForma ?? $analysis->huellaDigital,
                        'embedding' => $embedding,
                    ],
                ];
            }
        }

        // 4. BÚSQUEDA HÍBRIDA: Vectorial (Cosine) + Metadatos (Tipo de Prenda y Color)
        $tipoExtraido = $analysis->tipoPrenda ? mb_strtolower(trim($analysis->tipoPrenda)) : null;

        $variantesActivas = ProductVariant::whereHas('product', function ($q) {
            $q->where('status', Product::ESTADO_DISPONIBLE);
        })
            ->whereNotNull('image_embedding')
            ->with('product')
            ->get();

        $resultados = [];

        foreach ($variantesActivas as $variante) {
            $stockTotal = is_array($variante->sizes_stock) ? array_sum($variante->sizes_stock) : 0;
            if ($stockTotal <= 0 || ! is_array($variante->image_embedding)) {
                continue;
            }

            $similarity = $this->cosineSimilarity($embedding, $variante->image_embedding);

            // --- INICIO DE BÚSQUEDA HÍBRIDA (Descarte Estricto y Boosts) ---
            $visionProfileV = is_array($variante->product?->vision_profile) ? $variante->product->vision_profile : [];

            // 1. DESCARTE por Tipo de Prenda
            $tipoV = $visionProfileV['tipo_prenda'] ?? null;
            if ($tipoExtraido && $tipoV) {
                if (mb_strtolower(trim($tipoV)) !== $tipoExtraido) {
                    continue; // MÉTODO POR DESCARTE: Si uno es pantalón y el otro vestido, lo ignoramos por completo
                } else {
                    $similarity *= 1.15; // Boost si coinciden
                }
            }

            // 2. DESCARTE por Tipo de Manga
            $mangaV = $visionProfileV['zona_superior']['manga_tipo'] ?? null;
            $mangaE = $analysis->zonaSuperior['manga_tipo'] ?? null;
            if ($mangaE && $mangaV) {
                $mV = mb_strtolower(trim($mangaV));
                $mE = mb_strtolower(trim($mangaE));

                // Grupos estrictos de mangas (separando tirantes de manga cero/halter)
                $grupoLarga = ['larga', 'tres cuartos', 'campana', 'globo'];
                $grupoCorta = ['corta'];
                $grupoTirantes = ['tirantes', 'strapless', 'off-shoulder', 'asimétrica'];
                $grupoCeroHalter = ['cero', 'halter'];

                $getGrupoManga = function ($m) use ($grupoLarga, $grupoCorta, $grupoTirantes, $grupoCeroHalter) {
                    if (in_array($m, $grupoLarga)) {
                        return 1;
                    }
                    if (in_array($m, $grupoCorta)) {
                        return 2;
                    }
                    if (in_array($m, $grupoTirantes)) {
                        return 3;
                    }
                    if (in_array($m, $grupoCeroHalter)) {
                        return 4;
                    }

                    return 0; // Desconocido, no descartar
                };

                $gV = $getGrupoManga($mV);
                $gE = $getGrupoManga($mE);

                if ($gV !== 0 && $gE !== 0 && $gV !== $gE) {
                    continue; // MÉTODO POR DESCARTE: Mangas estructuralmente diferentes (ej: tirantes vs cuello alto)
                }
            }

            // 2.5 DESCARTE por Tipo y Apertura de Cuello
            $cuelloV = $visionProfileV['zona_cuello']['tipo'] ?? null;
            $cuelloE = $analysis->zonaCuello['tipo'] ?? null;
            $aperturaV = $visionProfileV['zona_cuello']['apertura'] ?? null;
            $aperturaE = $analysis->zonaCuello['apertura'] ?? null;

            if ($cuelloE && $cuelloV) {
                $cV = mb_strtolower(trim($cuelloV));
                $cE = mb_strtolower(trim($cuelloE));

                $grupoAlto = ['alto', 'tortuga', 'cisne'];
                $grupoBasico = ['redondo', 'cuadrado', 'en v', 'profundo', 'bote'];
                $grupoDesc = ['halter', 'off-shoulder', 'strapless', 'asimétrico', 'corazón'];
                $grupoCamisero = ['camisero', 'solapa'];

                $getGrupoCuello = function ($c) use ($grupoAlto, $grupoBasico, $grupoDesc, $grupoCamisero) {
                    if (in_array($c, $grupoAlto)) {
                        return 1;
                    }
                    if (in_array($c, $grupoBasico)) {
                        return 2;
                    }
                    if (in_array($c, $grupoDesc)) {
                        return 3;
                    }
                    if (in_array($c, $grupoCamisero)) {
                        return 4;
                    }

                    return 0;
                };

                $gcV = $getGrupoCuello($cV);
                $gcE = $getGrupoCuello($cE);

                if ($gcV !== 0 && $gcE !== 0) {
                    if ($gcV === $gcE) {
                        $similarity *= 1.05; // Bonus si el tipo coincide
                    } else {
                        $similarity *= 0.90; // Penalización ligera si el tipo difiere
                    }
                }
            }

            // Penalización por apertura de cuello (abierto vs cerrado)
            if ($aperturaE && $aperturaV) {
                $apV = mb_strtolower(trim($aperturaV));
                $apE = mb_strtolower(trim($aperturaE));
                if ($apV !== $apE) {
                    $similarity *= 0.80; // Penalización del 20% si uno es escote abierto y el otro cuello cerrado
                }
            }

            // 3. DESCARTE por Patrón Estricto
            $patronV = $visionProfileV['zona_superior']['patron'] ?? null;
            $patronE = $analysis->zonaSuperior['patron'] ?? null;
            if ($patronE && $patronV) {
                $pV = mb_strtolower(trim($patronV));
                $pE = mb_strtolower(trim($patronE));

                // Grupos de patrones (separados de forma estricta para evitar falsos positivos)
                $grupoLiso = ['liso'];
                $grupoRayas = ['rayas', 'rayas (líneas rectas)'];
                $grupoOndas = ['ondas', 'ondas (líneas curvas)'];
                $grupoZigzag = ['zigzag', 'zigzag (líneas en picos)'];
                $grupoFloral = ['floral', 'abstracto', 'floral / hojas', 'abstracto / manchas'];
                $grupoGeo = ['geométrico', 'cuadros', 'geométrico cerrado (cuadros/rombos/círculos)'];
                $grupoAnimal = ['animal print'];

                $getGrupoPatron = function ($p) use ($grupoLiso, $grupoRayas, $grupoOndas, $grupoZigzag, $grupoFloral, $grupoGeo, $grupoAnimal) {
                    if (in_array($p, $grupoLiso)) {
                        return 1;
                    }
                    if (in_array($p, $grupoRayas)) {
                        return 2;
                    }
                    if (in_array($p, $grupoOndas)) {
                        return 3;
                    }
                    if (in_array($p, $grupoZigzag)) {
                        return 4;
                    }
                    if (in_array($p, $grupoFloral)) {
                        return 5;
                    }
                    if (in_array($p, $grupoGeo)) {
                        return 6;
                    }
                    if (in_array($p, $grupoAnimal)) {
                        return 7;
                    }

                    return 0;
                };

                $gpV = $getGrupoPatron($pV);
                $gpE = $getGrupoPatron($pE);

                if ($gpV !== 0 && $gpE !== 0 && $gpV !== $gpE) {
                    continue; // MÉTODO POR DESCARTE: El tipo de estampado (ondas vs zigzag) es totalmente distinto
                }
            }

            // 3.1 DESCARTE por Ajuste (Fit)
            $fitV = $visionProfileV['ajuste_fit'] ?? null;
            $fitE = $analysis->ajusteFit ?? null;
            if ($fitE && $fitV) {
                $fV = mb_strtolower(trim($fitV));
                $fE = mb_strtolower(trim($fitE));

                // Solo descartamos extremos: Bodycon vs Oversize
                $esAjustado = function ($f) {
                    return in_array($f, ['ajustado bodycon', 'ceñido']);
                };
                $esOversize = function ($f) {
                    return in_array($f, ['holgado oversize', 'ancho']);
                };

                if (($esAjustado($fV) && $esOversize($fE)) || ($esOversize($fV) && $esAjustado($fE))) {
                    $similarity *= 0.85; // Penalización media por diferencia extrema de fit, pero no descartar
                } elseif ($fV === $fE) {
                    $similarity *= 1.05; // Bonus por fit exacto
                }
            }

            // 3.2 DESCARTE por Caída (Zona Inferior)
            $caidaV = $visionProfileV['zona_inferior']['caida'] ?? null;
            $caidaE = $analysis->zonaInferior['caida'] ?? null;
            if ($caidaE && $caidaV) {
                $cdV = mb_strtolower(trim($caidaV));
                $cdE = mb_strtolower(trim($caidaE));

                $esTubo = function ($c) {
                    return in_array($c, ['tubo', 'recta', 'lápiz']);
                };
                $esVuelo = function ($c) {
                    return in_array($c, ['campana', 'vuelo', 'línea a', 'plisado', 'acampanada', 'sirena']);
                };

                if (($esTubo($cdV) && $esVuelo($cdE)) || ($esVuelo($cdV) && $esTubo($cdE))) {
                    continue; // MÉTODO POR DESCARTE: Tubo vs Vuelo
                }
            }

            // 3.5 DESCARTE por Largo (Zona Inferior)
            $largoV = $visionProfileV['zona_inferior']['largo'] ?? null;
            $largoE = $analysis->zonaInferior['largo'] ?? null;
            if ($largoE && $largoV) {
                $lV = mb_strtolower(trim($largoV));
                $lE = mb_strtolower(trim($largoE));

                $grupoCorto = ['mini (sobre rodilla)', 'corto'];
                $grupoMidi = ['midi (a media pierna)'];
                $grupoMaxi = ['maxi (hasta tobillo/piso)'];

                $getGrupoLargo = function ($l) use ($grupoCorto, $grupoMidi, $grupoMaxi) {
                    if (in_array($l, $grupoCorto)) {
                        return 1;
                    }
                    if (in_array($l, $grupoMidi)) {
                        return 2;
                    }
                    if (in_array($l, $grupoMaxi)) {
                        return 3;
                    }

                    return 0;
                };

                $glV = $getGrupoLargo($lV);
                $glE = $getGrupoLargo($lE);

                // Solo descartar si la diferencia es extrema (Mini vs Maxi).
                // Midi y Maxi a veces se confunden por el ángulo de la foto, por lo que no se penalizan.
                if (($glV === 1 && $glE === 3) || ($glV === 3 && $glE === 1)) {
                    continue; // MÉTODO POR DESCARTE: Un vestido mini nunca será un vestido maxi
                }
            }

            // 3.7 DESCARTE por Distribución de Color (Degradado vs Bloques/Rayas)
            $distV = $visionProfileV['paleta_colores']['distribucion'] ?? null;
            $distE = $analysis->paletaColores['distribucion'] ?? null;
            if ($distE && $distV) {
                $dV = mb_strtolower(trim($distV));
                $dE = mb_strtolower(trim($distE));

                // Si uno es claramente un degradado y el otro no lo es (ej. es a rayas/bloques), son distintos.
                $esDegradado = function ($d) {
                    return str_contains($d, 'degradado');
                };

                if (($esDegradado($dV) && ! $esDegradado($dE)) || ($esDegradado($dE) && ! $esDegradado($dV))) {
                    continue; // MÉTODO POR DESCARTE: Un vestido degradado no es un vestido a rayas/bloques
                }
            }

            // 3.9 PENALIZACIÓN por Cinturón (Descarte/Ajuste Estructural)
            // Si uno incluye cinturón como parte de su diseño y el otro no, no pueden ser el mismo modelo.
            $cinturonV = $visionProfileV['zona_cintura']['detalle'] ?? null;
            $extrasV = $visionProfileV['detalles_constructivos']['extras'] ?? [];
            $tieneCinturonV = ($cinturonV === 'cinturón') || in_array('cinturón incluido', (array) $extrasV);

            $cinturonE = $analysis->zonaCintura['detalle'] ?? null;
            $extrasE = $analysis->detallesConstructivos['extras'] ?? [];
            $tieneCinturonE = ($cinturonE === 'cinturón') || in_array('cinturón incluido', (array) $extrasE);

            if ($tieneCinturonV !== $tieneCinturonE) {
                $similarity *= 0.70; // Penalización severa del 30% por discrepancia estructural de accesorio clave
            }

            // 4. Boost por Color Principal
            $colorV = $visionProfileV['paleta_colores']['colores'][0] ?? $variante->color ?? null;
            $esMismoColor = false;
            if ($colorExtraido && $colorV) {
                // Validación un poco más flexible para el color principal
                if (str_contains(mb_strtolower(trim($colorV)), mb_strtolower(trim($colorExtraido))) ||
                    str_contains(mb_strtolower(trim($colorExtraido)), mb_strtolower(trim($colorV)))) {
                    $similarity *= 1.10;
                    $esMismoColor = true;
                }
            }
            // --- FIN BÚSQUEDA HÍBRIDA ---

            $resultados[] = [
                'variant' => $variante,
                'similarity' => min($similarity, 1.0),
                'es_mismo_color' => $esMismoColor,
            ];
        }

        usort($resultados, fn ($a, $b) => $b['similarity'] <=> $a['similarity']);

        // UMBRALES: Ahora que los embeddings se generan correctamente con la huella_forma
        // (sin colores), los puntajes de similitud son mucho más altos para vestidos realmente
        // parecidos. Exigimos un 0.85 de similitud neta para considerarlo un match exacto,
        // evitando falsos positivos con vestidos que no están en el catálogo.
        $umbralExacto = 0.85;

        $exactMatch = null;

        if (! empty($resultados)) {
            $mejor = $resultados[0];
            if ($mejor['similarity'] >= $umbralExacto) {
                $exactMatch = $mejor;
            }
        }

        if ($exactMatch !== null) {
            $variante = $exactMatch['variant'];
            Log::info('ImageAnalyzer: Producto encontrado por similitud vectorial EXACTA', [
                'id_producto' => $variante->product_id,
                'similitud' => $exactMatch['similarity'],
            ]);

            return [
                'caption' => 'Producto reconocido exactamente por vector descriptivo',
                'inbound_profile' => [
                    'encontrado' => true,
                    'matches' => [[
                        'id_producto' => $variante->product_id,
                        'nombre_vestido' => $variante->product->name ?? 'Desconocido',
                        'color' => $variante->color,
                        'similitud' => $exactMatch['similarity'],
                        'image_url' => $this->mediaProducto->resolveAbsolutePublicUrl($variante),
                        'es_mismo_color' => $exactMatch['es_mismo_color'],
                    ]],
                    'tipo_mensaje' => 'producto',
                    'caption_cliente' => $captionCliente,
                    'huella_forma' => $analysis->huellaForma ?? $analysis->huellaDigital,
                    'embedding' => $embedding,
                ],
            ];
        }

        Log::info('ImageAnalyzer: No se encontró similitud vectorial aceptable. Se devolverá no encontrado.');

        return [
            'caption' => 'Producto no reconocido con certeza',
            'inbound_profile' => [
                'encontrado' => false,
                'matches' => [],
                'tipo_mensaje' => 'producto',
                'caption_cliente' => $captionCliente,
                'huella_forma' => $analysis->huellaForma ?? $analysis->huellaDigital,
                'embedding' => $embedding,
            ],
        ];
    }

    private function cosineSimilarity(array $vec1, array $vec2): float
    {
        $dotProduct = 0.0;
        $norm1 = 0.0;
        $norm2 = 0.0;
        $count = min(count($vec1), count($vec2));

        for ($i = 0; $i < $count; $i++) {
            $v1 = (float) $vec1[$i];
            $v2 = (float) $vec2[$i];

            $dotProduct += $v1 * $v2;
            $norm1 += $v1 * $v1;
            $norm2 += $v2 * $v2;
        }

        if ($norm1 == 0.0 || $norm2 == 0.0) {
            return 0.0;
        }

        return $dotProduct / (sqrt($norm1) * sqrt($norm2));
    }
}
