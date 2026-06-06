<?php

namespace Tests\Unit;

use App\Services\ClienteGemini;
use ReflectionMethod;
use Tests\TestCase;

class ClienteGeminiTest extends TestCase
{
    public function test_limpia_respuesta_preserva_listas_y_negrita(): void
    {
        $entrada = <<<'TXT'
¡Excelente! Envíanos los siguientes datos:

✅ Nombre completo
✅ **DNI:** 12345678
✅ Celular

Los envíos por Shalom son lun/mié/vie.
TXT;

        $resultado = $this->limpiarRespuesta($entrada);

        $this->assertStringContainsString("✅ Nombre completo\n", $resultado);
        $this->assertStringContainsString('**DNI:**', $resultado);
        $this->assertStringContainsString('Shalom', $resultado);
        $this->assertStringNotContainsString("\n\n\n", $resultado);
    }

    public function test_limpia_respuesta_quita_pensamientos_en_cursiva_simple(): void
    {
        $entrada = 'Hola hermosa *piensa en stock* ¿te gusta el vestido?';

        $resultado = $this->limpiarRespuesta($entrada);

        $this->assertStringNotContainsString('piensa', $resultado);
        $this->assertStringContainsString('¿te gusta el vestido?', $resultado);
    }

    private function limpiarRespuesta(string $texto): string
    {
        $cliente = new ClienteGemini('test-key');
        $method = new ReflectionMethod(ClienteGemini::class, 'limpiarRespuesta');
        $method->setAccessible(true);

        return $method->invoke($cliente, $texto);
    }
}
