<?php

namespace App\Services;

class ResultadoGeminiAgente
{
    public function __construct(
        public readonly string $texto,
        public readonly int $iteraciones = 1,
    ) {}
}
