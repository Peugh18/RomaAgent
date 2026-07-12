<?php

namespace App\DTOs;

class GarmentAnalysisResult
{
    public function __construct(
        public bool $esPrenda,
        public ?string $tipoPrenda,
        public array $zonaCuello,
        public array $zonaSuperior,
        public array $zonaCintura,
        public array $zonaInferior,
        public array $paletaColores,
        public array $detallesConstructivos,
        public ?string $ajusteFit,
        public ?string $huellaDigital,
        public array $rawJson = [],

        // Campos de conveniencia derivados
        public ?string $colorPrincipal = null,
        public ?string $descripcionVectorial = null,
        public bool $esComprobante = false,
        public ?string $huellaForma = null,
        public array $analisisUltraDetallado = [],
    ) {}

    /**
     * @param  array<string, mixed>  $json
     */
    public static function fromJson(array $json): self
    {
        $zonaCuello = (array) ($json['zona_cuello'] ?? []);
        $zonaSuperior = (array) ($json['zona_superior'] ?? []);
        $zonaCintura = (array) ($json['zona_cintura'] ?? []);
        $zonaInferior = (array) ($json['zona_inferior'] ?? []);
        $paletaColores = (array) ($json['paleta_colores'] ?? []);
        $detallesConstructivos = (array) ($json['detalles_constructivos'] ?? []);
        $analisisUltraDetallado = (array) ($json['analisis_ultra_detallado'] ?? []);

        // Derivar color_principal desde la paleta (primer color) o zona_superior
        $colores = (array) ($paletaColores['colores'] ?? []);
        $colorPrincipal = $colores[0]
            ?? $zonaSuperior['color']
            ?? $json['color_principal']
            ?? null;

        $huellaDigital = $json['huella_digital']
            ?? $json['descripcion_vectorial']
            ?? null;

        // huella_forma: descripción SIN colores, ideal para embeddings cross-color
        $huellaForma = $json['huella_forma'] ?? null;

        // Enriquecer la descripción vectorial con la descripción ultra-detallada del patrón
        // para que el embedding capture diferencias finas (rayas verticales vs horizontales, etc.)
        $descripcionPatron = trim((string) ($analisisUltraDetallado['descripcion_patron'] ?? ''));
        $descripcionTextura = trim((string) ($analisisUltraDetallado['descripcion_textura'] ?? ''));

        $baseDescripcion = $huellaForma ?? $huellaDigital;
        $partes = array_filter([$baseDescripcion, $descripcionPatron, $descripcionTextura]);
        $descripcionVectorial = implode(' | ', $partes) ?: null;

        return new self(
            esPrenda: $json['es_prenda'] ?? false,
            tipoPrenda: $json['tipo_prenda'] ?? null,
            zonaCuello: $zonaCuello,
            zonaSuperior: $zonaSuperior,
            zonaCintura: $zonaCintura,
            zonaInferior: $zonaInferior,
            paletaColores: $paletaColores,
            detallesConstructivos: $detallesConstructivos,
            ajusteFit: $json['ajuste_fit'] ?? null,
            huellaDigital: $huellaDigital,
            rawJson: $json,
            colorPrincipal: $colorPrincipal,
            descripcionVectorial: $descripcionVectorial,
            esComprobante: $json['es_comprobante'] ?? false,
            huellaForma: $huellaForma,
            analisisUltraDetallado: $analisisUltraDetallado,
        );
    }
}
