<?php

namespace App\DTOs;

class GarmentAnalysisResult
{
    public function __construct(
        public bool $esPrenda,
        public ?string $tipoPrenda,
        public ?string $largoPrenda,
        public ?string $manga,
        public ?string $cuello,
        public ?string $ajusteFit,
        public ?string $patron,
        public ?string $texturaTejido,
        public ?string $distribucionColor,
        public ?string $colorPrincipal,
        public array $acabadosBordes,
        public array $detallesClave,
        public ?string $descripcionVectorial,
        public array $rawJson = []
    ) {}

    public static function fromJson(array $json): self
    {
        return new self(
            esPrenda: $json['es_prenda'] ?? false,
            tipoPrenda: $json['tipo_prenda'] ?? null,
            largoPrenda: $json['largo_prenda'] ?? null,
            manga: $json['manga'] ?? null,
            cuello: $json['cuello'] ?? null,
            ajusteFit: $json['ajuste_fit'] ?? null,
            patron: $json['patron'] ?? null,
            texturaTejido: $json['textura_tejido'] ?? null,
            distribucionColor: $json['distribucion_color'] ?? null,
            colorPrincipal: $json['color_principal'] ?? null,
            acabadosBordes: (array) ($json['acabados_bordes'] ?? []),
            detallesClave: (array) ($json['detalles_clave'] ?? []),
            descripcionVectorial: $json['descripcion_vectorial'] ?? null,
            rawJson: $json
        );
    }
}
