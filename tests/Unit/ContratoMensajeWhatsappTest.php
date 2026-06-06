<?php

namespace Tests\Unit;

use App\Support\ContratoMensajeWhatsapp;
use PHPUnit\Framework\TestCase;

class ContratoMensajeWhatsappTest extends TestCase
{
    public function test_extrae_ubicacion_desde_payload_de_roma_api(): void
    {
        $payload = [
            'message_type' => 'location',
            'location' => [
                'latitude' => -12.05,
                'longitude' => -77.03,
                'address' => 'Av. Arequipa 123',
            ],
        ];

        $ubicacion = ContratoMensajeWhatsapp::extraerUbicacion($payload);

        $this->assertNotNull($ubicacion);
        $this->assertSame(-12.05, $ubicacion['latitude']);
        $this->assertSame(-77.03, $ubicacion['longitude']);
        $this->assertSame('Av. Arequipa 123', $ubicacion['address']);
    }

    public function test_inbound_metadata_marca_tipo_location(): void
    {
        $payload = [
            'message_type' => 'location',
            'location' => [
                'latitude' => -11.9,
                'longitude' => -77.1,
            ],
        ];

        $meta = ContratoMensajeWhatsapp::inboundMetadata($payload);

        $this->assertSame('location', $meta['type']);
        $this->assertSame(-11.9, $meta['latitude']);
        $this->assertSame(-77.1, $meta['longitude']);
        $this->assertStringContainsString('google.com/maps', (string) $meta['maps_url']);
    }

    public function test_inbound_content_usa_etiqueta_de_ubicacion(): void
    {
        $payload = [
            'message_type' => 'location',
            'location' => [
                'latitude' => -11.9,
                'longitude' => -77.1,
                'name' => 'Casa clienta',
            ],
        ];

        $this->assertSame(
            '📍 Ubicación compartida: Casa clienta',
            ContratoMensajeWhatsapp::inboundContent($payload),
        );
    }
}
