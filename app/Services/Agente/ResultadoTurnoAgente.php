<?php

namespace App\Services\Agente;

class ResultadoTurnoAgente
{
    /**
     * @param  list<array{text: string, image_url?: string|null}>  $mensajesSalientes
     */
    public function __construct(
        public readonly ?string $textoFinal,
        public readonly array $mensajesSalientes = [],
        public readonly bool $iaPausada = false,
        public readonly int $iteraciones = 0,
    ) {}
}
