<?php

namespace Tests\Unit;

use App\Models\AgenteConfig;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class AgenteConfigApiKeyTest extends TestCase
{
    public function test_normaliza_api_key_legacy_serializada(): void
    {
        $serializada = 's:39:"AIzaSyB5ngjR_test_key_for_unit_tests";';

        $this->assertSame(
            'AIzaSyB5ngjR_test_key_for_unit_tests',
            AgenteConfig::normalizarApiKeyPlana($serializada),
        );
    }

    public function test_obtener_api_key_desde_encrypt_string(): void
    {
        $config = new AgenteConfig([
            'api_key_encrypted' => Crypt::encryptString('AIzaSy_test_modern_key'),
        ]);

        $this->assertSame('AIzaSy_test_modern_key', $config->obtenerApiKey());
    }

    public function test_obtener_api_key_desde_encrypt_legacy(): void
    {
        $config = new AgenteConfig([
            'api_key_encrypted' => encrypt('AIzaSy_test_legacy_key'),
        ]);

        $this->assertSame('AIzaSy_test_legacy_key', $config->obtenerApiKey());
    }
}
