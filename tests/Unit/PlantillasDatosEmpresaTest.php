<?php

namespace Tests\Unit;

use App\Support\PlantillasDatosEmpresa;
use Tests\TestCase;

class PlantillasDatosEmpresaTest extends TestCase
{
    public function test_normaliza_array_vacio_a_defecto(): void
    {
        $resultado = PlantillasDatosEmpresa::normalizar([]);

        $this->assertArrayHasKey('motorizado', $resultado);
        $this->assertArrayHasKey('shalom', $resultado);
        $this->assertStringContainsString('DNI', implode(' ', $resultado['shalom']));
    }

    public function test_preserva_plantillas_personalizadas(): void
    {
        $resultado = PlantillasDatosEmpresa::normalizar([
            'motorizado' => ['x' => 'Campo custom motorizado'],
            'shalom' => ['y' => 'Campo custom shalom'],
        ]);

        $this->assertSame('Campo custom motorizado', $resultado['motorizado']['x']);
        $this->assertSame('Campo custom shalom', $resultado['shalom']['y']);
    }
}
