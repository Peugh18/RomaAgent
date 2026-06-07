<?php

namespace Tests\Unit;

use App\Support\ValidadorPlantillaMensaje;
use Tests\TestCase;

class ValidadorPlantillaMensajeTest extends TestCase
{
    public function test_detecta_formato_incorrecto_con_json_literal(): void
    {
        $this->assertTrue(
            ValidadorPlantillaMensaje::tieneFormatoIncorrecto('listo hermosa; {"Mariela"}, {"Lila"}, {"190.00"}')
        );
    }

    public function test_normalizar_rechaza_plantilla_incorrecta(): void
    {
        $this->assertSame(
            '',
            ValidadorPlantillaMensaje::normalizar('listo {"Mariela"}')
        );
    }

    public function test_normalizar_acepta_variables_validas(): void
    {
        $this->assertSame(
            'Hola {nombre}, {producto} S/ {total}',
            ValidadorPlantillaMensaje::normalizar('Hola {nombre}, {producto} S/ {total}')
        );
    }
}
