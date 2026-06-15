<?php

namespace Tests\Unit;

use App\Models\CompanySetting;
use App\Support\NormalizadorStockTallas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NormalizadorStockTallasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CompanySetting::factory()->create([
            'standard_size' => 'UNICA',
        ]);
    }

    public function test_etiqueta_publica_convierte_unica_a_talla_estandar(): void
    {
        $this->assertSame('estándar', NormalizadorStockTallas::etiquetaPublica('UNICA'));
        $this->assertSame('estándar', NormalizadorStockTallas::etiquetaPublica('unica'));
        $this->assertSame('M', NormalizadorStockTallas::etiquetaPublica('M'));
    }

    public function test_instruccion_prompt_prohibe_decir_unica(): void
    {
        $texto = NormalizadorStockTallas::instruccionTallaParaPrompt();

        $this->assertStringContainsString('talla estándar', $texto);
        $this->assertStringContainsString('Nunca', $texto);
        $this->assertStringContainsString('única', $texto);
        $this->assertStringContainsString('UNICA', $texto);
    }

    public function test_instruccion_catalogo_no_expone_codigo_como_etiqueta_cliente(): void
    {
        $texto = NormalizadorStockTallas::instruccionTallaParaCatalogo();

        $this->assertStringContainsString('talla estándar', $texto);
        $this->assertStringContainsString('nunca "única"', $texto);
    }
}
