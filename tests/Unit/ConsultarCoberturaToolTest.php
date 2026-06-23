<?php

namespace Tests\Unit;

use App\Models\ZonaEnvio;
use App\Services\Agente\Tools\ConsultarCoberturaTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsultarCoberturaToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_consultar_cobertura_parses_comma_separated_locations(): void
    {
        // 1. Create a ZonaEnvio in Trujillo
        $zona = ZonaEnvio::create([
            'distrito' => 'Trujillo',
            'provincia' => 'Trujillo',
            'departamento' => 'La Libertad',
            'tipo_envio' => 'motorizado',
            'costo_referencial' => 15.00,
            'activo' => true,
        ]);

        // 2. Query with "La Libertad, Trujillo, Trujillo"
        $res = ConsultarCoberturaTool::execute(['distrito' => 'La Libertad, Trujillo, Trujillo']);

        $this->assertTrue($res['ok']);
        $this->assertTrue($res['encontrado']);
        $this->assertSame('Trujillo', $res['distrito']);
        $this->assertSame('La Libertad', $res['departamento']);
        $this->assertSame('motorizado', $res['tipo_envio']);
        $this->assertSame(15.00, $res['costo_referencial']);

        // 3. Query with "Trujillo, La Libertad"
        $res2 = ConsultarCoberturaTool::execute(['distrito' => 'Trujillo, La Libertad']);
        $this->assertTrue($res2['ok']);
        $this->assertTrue($res2['encontrado']);

        // 4. Query with Shalom city "Chimbote, Ancash" (not in ZonaEnvio)
        $res3 = ConsultarCoberturaTool::execute(['distrito' => 'Chimbote, Ancash']);
        $this->assertTrue($res3['ok']);
        $this->assertFalse($res3['encontrado']);
        $this->assertSame('Chimbote', $res3['distrito']);
        $this->assertSame('Ancash', $res3['departamento']);
        $this->assertSame('shalom', $res3['tipo_envio']);
        $this->assertSame('Pago en destino', $res3['costo_referencial']);
    }
}
