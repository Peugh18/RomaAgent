<?php

namespace Tests\Unit;

use App\Enums\SaleStatus;
use App\Models\Sale;
use App\Support\NormalizadorRespuestaAgente;
use Tests\TestCase;

class NormalizadorRespuestaAgenteTest extends TestCase
{
    public function test_normaliza_dolares_a_soles_cuando_moneda_es_pen(): void
    {
        $normalizador = new NormalizadorRespuestaAgente;

        $texto = $normalizador->procesar('El total es $190.00 con envío de $10.00', 'PEN');

        $this->assertStringContainsString('S/ 190.00', $texto);
        $this->assertStringContainsString('S/ 10.00', $texto);
        $this->assertStringNotContainsString('$', $texto);
    }

    public function test_reemplaza_talla_unica_por_talla_estandar(): void
    {
        $normalizador = new NormalizadorRespuestaAgente;

        $texto = $normalizador->procesar('Disponible en talla única y UNICA', 'PEN');

        $this->assertStringNotContainsString('única', mb_strtolower($texto));
        $this->assertStringNotContainsString('UNICA', $texto);
        $this->assertStringContainsString('talla estándar', $texto);
    }

    public function test_corrige_total_de_una_unidad_cuando_pedido_tiene_cantidad_mayor(): void
    {
        $sale = new Sale([
            'quantity' => 3,
            'unit_price' => 180,
            'delivery_cost' => 10,
            'total_amount' => 550,
            'status' => SaleStatus::DatosListos,
        ]);

        $normalizador = new NormalizadorRespuestaAgente;
        $texto = $normalizador->procesar(
            'Hermosa, tu total es S/ 190.00 (producto + envío).',
            'PEN',
            $sale,
        );

        $this->assertStringContainsString('550.00', $texto);
        $this->assertStringNotContainsString('190.00', $texto);
    }

    public function test_parte_mensajes_por_separador_split(): void
    {
        $normalizador = new NormalizadorRespuestaAgente;

        $partes = $normalizador->partirEnMensajes(
            "Hola hermosa\n\n---SPLIT---\n\nTu total es S/ 540\n\n---SPLIT---\n\nTe paso el Yape",
        );

        $this->assertCount(3, $partes);
        $this->assertSame('Hola hermosa', $partes[0]);
        $this->assertSame('Tu total es S/ 540', $partes[1]);
        $this->assertSame('Te paso el Yape', $partes[2]);
    }

    public function test_limita_a_tres_partes_cuando_hay_mas_separadores_split(): void
    {
        $normalizador = new NormalizadorRespuestaAgente;

        $partes = $normalizador->partirEnMensajes(
            'Uno---SPLIT---Dos---SPLIT---Tres---SPLIT---Cuatro',
        );

        $this->assertCount(3, $partes);
    }

    public function test_parte_mensaje_largo_en_un_parrafo_por_oraciones(): void
    {
        $normalizador = new NormalizadorRespuestaAgente;

        $partes = $normalizador->partirEnMensajes(
            '¡Claro, hermosa! Teníamos un pedido activo del vestido Mariela en color Lila, talla estándar, con envío por motorizado a Ate. '
            .'El total a cobrar es de S/ 190.00. '
            .'¿Deseas que continuemos con este pedido o te gustaría consultar por otro vestido? 😊',
        );

        $this->assertGreaterThanOrEqual(2, count($partes));
        $this->assertLessThanOrEqual(NormalizadorRespuestaAgente::MAX_PARTES, count($partes));
        $this->assertStringContainsString('190.00', implode(' ', $partes));
        $this->assertStringContainsString('¿Deseas', end($partes));
    }

    public function test_no_parte_mensajes_cortos(): void
    {
        $normalizador = new NormalizadorRespuestaAgente;

        $partes = $normalizador->partirEnMensajes('Sí hermosa, te envío la foto ahora 😊');

        $this->assertSame(['Sí hermosa, te envío la foto ahora 😊'], $partes);
    }

    public function test_parte_mensajes_por_linea_sola_con_guiones(): void
    {
        $normalizador = new NormalizadorRespuestaAgente;

        $partes = $normalizador->partirEnMensajes(
            "¡Hola, hermosa! Entendí que buscas el vestido Mariela, ¡y que te quede espectacular! ✨\n---\nLo tenemos disponible en talla estándar. ¿En qué color te gustaría, bella? Tenemos lila, azul o camel.",
        );

        $this->assertCount(2, $partes);
        $this->assertStringContainsString('Mariela', $partes[0]);
        $this->assertStringContainsString('talla estándar', $partes[1]);
        $this->assertStringNotContainsString('---', $partes[0]);
        $this->assertStringNotContainsString('---', $partes[1]);
    }
}
