<?php

namespace Tests\Unit;

use App\Services\Agente\EjecutorHerramientasAgente;
use Tests\TestCase;

class EjecutorHerramientasAgenteTest extends TestCase
{
    public function test_tool_definitions_encode_properties_as_json_objects(): void
    {
        $definiciones = app(EjecutorHerramientasAgente::class)->definiciones();

        $consultarPedido = collect($definiciones)->firstWhere('name', 'consultar_pedido_activo');

        $this->assertNotNull($consultarPedido);

        $json = json_encode($consultarPedido['parameters'], JSON_THROW_ON_ERROR);

        $this->assertStringContainsString('"properties":{}', str_replace(' ', '', $json));
        $this->assertStringNotContainsString('"properties":[]', $json);
    }
}
