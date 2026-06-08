<?php

namespace Tests\Unit;

use App\Support\Vision\ParseadorRespuestaJsonGemini;
use Tests\TestCase;

class ParseadorRespuestaJsonGeminiTest extends TestCase
{
    public function test_recupera_campos_de_json_truncado_de_produccion(): void
    {
        $truncado = "{\n  \"tipo\": \"producto\",\n  \"es_comprobante\": false,\n  \"es_captura_red";

        $profile = ParseadorRespuestaJsonGemini::parse($truncado);

        $this->assertIsArray($profile);
        $this->assertSame('producto', $profile['tipo']);
        $this->assertFalse($profile['es_comprobante']);
        $this->assertTrue($profile['es_captura_redes']);
    }

    public function test_parsea_json_completo(): void
    {
        $json = json_encode([
            'tipo' => 'producto',
            'tipo_prenda' => 'vestido',
            'color_dominante' => 'rojo',
        ]);

        $profile = ParseadorRespuestaJsonGemini::parse($json);

        $this->assertSame('vestido', $profile['tipo_prenda']);
        $this->assertSame('rojo', $profile['color_dominante']);
    }
}
