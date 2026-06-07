<?php

namespace Tests\Unit;

use App\Support\SanitizadorMetodosPago;
use PHPUnit\Framework\TestCase;

class SanitizadorMetodosPagoTest extends TestCase
{
    public function test_elimina_data_uri_embebido_en_imagen_url(): void
    {
        $metodos = [
            [
                'nombre' => 'Yape',
                'imagen_url' => 'data:image/png;base64,'.str_repeat('A', 5000),
            ],
        ];

        $resultado = SanitizadorMetodosPago::sanitizar($metodos);

        $this->assertArrayNotHasKey('imagen_url', $resultado[0]);
        $this->assertSame('Yape', $resultado[0]['nombre']);
    }

    public function test_conserva_url_http_normal(): void
    {
        $metodos = [
            [
                'nombre' => 'Yape',
                'imagen_url' => 'https://example.com/yape.png',
            ],
        ];

        $resultado = SanitizadorMetodosPago::sanitizar($metodos);

        $this->assertSame('https://example.com/yape.png', $resultado[0]['imagen_url']);
    }
}
