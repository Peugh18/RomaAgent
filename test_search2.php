<?php

use App\Models\ProductVariant;
use App\Services\Vision\ProductEmbeddingService;
use App\Support\Vision\GarmentAnalysisResult;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$clientDesc = 'Vestido maxi bodycon de tejido de punto con patrón de ondas horizontales en tonos rojo, burdeos y rosa claro. Presenta cuello alto, diseño sin mangas y un ajuste ceñido al cuerpo. Incluye un cinturón integrado en la cintura con hebilla metálica central. La caída es tipo tubo hasta los tobillos con un acabado de borde liso y uniforme.';

$embService = app(ProductEmbeddingService::class);
$clientEmb = $embService->generarEmbeddingTexto($clientDesc);

// Mock client analysis
$analysis = new GarmentAnalysisResult;
$analysis->tipoPrenda = 'vestido';
$analysis->zonaSuperior = ['manga_tipo' => 'cero', 'patron' => 'ondas (líneas curvas)'];
$analysis->zonaCuello = ['tipo' => 'alto'];
$analysis->zonaInferior = ['caida' => 'tubo', 'largo' => 'maxi (hasta tobillo/piso)'];
$analysis->ajusteFit = 'ajustado bodycon';
$analysis->paletaColores = ['distribucion' => 'franjas / rayas repetitivas'];
$analysis->colorPrincipal = 'rojo';

$variantes = ProductVariant::with('product')->get();
foreach ($variantes as $variante) {
    if (! $variante->image_embedding) {
        continue;
    }
    $vEmb = $variante->image_embedding;

    $dotProduct = 0.0;
    $norm1 = 0.0;
    $norm2 = 0.0;
    for ($i = 0; $i < count($clientEmb); $i++) {
        $dotProduct += $clientEmb[$i] * $vEmb[$i];
        $norm1 += $clientEmb[$i] * $clientEmb[$i];
        $norm2 += $vEmb[$i] * $vEmb[$i];
    }
    $similarity = $norm1 == 0 || $norm2 == 0 ? 0 : $dotProduct / (sqrt($norm1) * sqrt($norm2));
    $baseSim = $similarity;

    // Apply filters
    $vp = $variante->product->vision_profile ?? [];
    if (($vp['tipo_prenda'] ?? '') && $analysis->tipoPrenda) {
        if (mb_strtolower(trim($vp['tipo_prenda'])) !== $analysis->tipoPrenda) {
            continue;
        }
        $similarity *= 1.15;
    }

    // Manga
    $mV = mb_strtolower(trim($vp['zona_superior']['manga_tipo'] ?? ''));
    $mE = $analysis->zonaSuperior['manga_tipo'];
    $grupoLarga = ['larga', 'tres cuartos', 'campana', 'globo'];
    $grupoCorta = ['corta'];
    $grupoCero = ['cero', 'tirantes', 'halter', 'off-shoulder', 'asimétrica'];
    $gV = in_array($mV, $grupoLarga) ? 1 : (in_array($mV, $grupoCorta) ? 2 : (in_array($mV, $grupoCero) ? 3 : 0));
    $gE = 3;
    if ($gV !== 0 && $gE !== 0 && $gV !== $gE) {
        continue;
    }

    // Cuello
    $cV = mb_strtolower(trim($vp['zona_cuello']['tipo'] ?? ''));
    $cE = $analysis->zonaCuello['tipo'];
    $gAlto = ['alto', 'tortuga', 'cisne'];
    $gBasico = ['redondo', 'cuadrado', 'en v', 'profundo', 'bote'];
    $gDesc = ['halter', 'off-shoulder', 'strapless', 'asimétrico', 'corazón'];
    $gcV = in_array($cV, $gAlto) ? 1 : (in_array($cV, $gBasico) ? 2 : (in_array($cV, $gDesc) ? 3 : 0));
    $gcE = 1;
    if ($gcV !== 0 && $gcE !== 0 && $gcV !== $gcE) {
        continue;
    }

    // Patron
    $pV = mb_strtolower(trim($vp['zona_superior']['patron'] ?? ''));
    $pE = $analysis->zonaSuperior['patron'];
    $gLin = ['rayas', 'ondas', 'zigzag', 'rayas (líneas rectas)', 'ondas (líneas curvas)', 'zigzag (líneas en picos)'];
    $gpV = in_array($pV, $gLin) ? 2 : 0;
    $gpE = 2;
    if ($gpV !== 0 && $gpE !== 0 && $gpV !== $gpE) {
        continue;
    }

    echo 'PASA FILTROS: '.$variante->product->name.' -> SimBase: '.round($baseSim, 3).' | SimBoosted: '.round($similarity, 3)."\n";
}
