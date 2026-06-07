<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\Message;
use App\Services\Agente\EjecutorHerramientasAgente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EjecutorHerramientasHumanoTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_pausa_ia_cuando_motivo_es_metodos_de_pago(): void
    {
        $customer = Customer::factory()->create(['ia_paused' => false]);
        $mensaje = Message::query()->create([
            'message_id' => 'wamid.test.pago',
            'phone_number' => $customer->phone_number,
            'content' => 'dime que metodos de pago tienes',
            'direction' => 'incoming',
            'status' => 'delivered',
            'metadata' => ['type' => 'text'],
        ]);

        $resultado = app(EjecutorHerramientasAgente::class)->ejecutar(
            'solicitar_atencion_humana',
            ['motivo' => 'Consulta sobre métodos de pago'],
            $customer,
            $mensaje,
        );

        $this->assertFalse($resultado['ok']);
        $this->assertFalse($resultado['ia_pausada']);
        $customer->refresh();
        $this->assertFalse($customer->ia_paused);
    }

    public function test_no_pausa_ia_cuando_mensaje_es_sticker(): void
    {
        $customer = Customer::factory()->create(['ia_paused' => false]);
        $mensaje = Message::query()->create([
            'message_id' => 'wamid.test.sticker',
            'phone_number' => $customer->phone_number,
            'content' => '🙂 Sticker',
            'direction' => 'incoming',
            'status' => 'delivered',
            'metadata' => ['type' => 'sticker'],
        ]);

        $resultado = app(EjecutorHerramientasAgente::class)->ejecutar(
            'solicitar_atencion_humana',
            ['motivo' => 'Clienta envió sticker'],
            $customer,
            $mensaje,
        );

        $this->assertFalse($resultado['ok']);
        $customer->refresh();
        $this->assertFalse($customer->ia_paused);
    }

    public function test_si_pausa_ia_cuando_motivo_es_tarjeta(): void
    {
        $customer = Customer::factory()->create(['ia_paused' => false]);
        $mensaje = Message::query()->create([
            'message_id' => 'wamid.test.tarjeta',
            'phone_number' => $customer->phone_number,
            'content' => 'quiero pagar con tarjeta',
            'direction' => 'incoming',
            'status' => 'delivered',
            'metadata' => ['type' => 'text'],
        ]);

        $resultado = app(EjecutorHerramientasAgente::class)->ejecutar(
            'solicitar_atencion_humana',
            ['motivo' => 'Pago con tarjeta — generar link'],
            $customer,
            $mensaje,
        );

        $this->assertTrue($resultado['ok']);
        $this->assertTrue($resultado['ia_pausada']);
        $customer->refresh();
        $this->assertTrue($customer->ia_paused);
    }

    public function test_si_pausa_ia_cuando_motivo_es_comprobante_aunque_mencione_yape(): void
    {
        $customer = Customer::factory()->create(['ia_paused' => false]);
        $mensaje = Message::query()->create([
            'message_id' => 'wamid.test.comprobante',
            'phone_number' => $customer->phone_number,
            'content' => '[imagen comprobante]',
            'direction' => 'incoming',
            'status' => 'delivered',
            'metadata' => ['type' => 'image'],
        ]);

        $resultado = app(EjecutorHerramientasAgente::class)->ejecutar(
            'solicitar_atencion_humana',
            ['motivo' => 'Comprobante Yape por verificar'],
            $customer,
            $mensaje,
        );

        $this->assertTrue($resultado['ok']);
        $customer->refresh();
        $this->assertTrue($customer->ia_paused);
    }

    public function test_registrar_comprobante_sigue_pausando_ia(): void
    {
        $customer = Customer::factory()->create(['ia_paused' => false]);
        $mensaje = Message::query()->create([
            'message_id' => 'wamid.test.comp.tool',
            'phone_number' => $customer->phone_number,
            'content' => '[imagen]',
            'direction' => 'incoming',
            'status' => 'delivered',
            'metadata' => ['type' => 'image'],
        ]);

        $resultado = app(EjecutorHerramientasAgente::class)->ejecutar(
            'registrar_comprobante_recibido',
            [],
            $customer,
            $mensaje,
        );

        $this->assertTrue($resultado['ok']);
        $this->assertTrue($resultado['ia_pausada']);
        $customer->refresh();
        $this->assertTrue($customer->ia_paused);
        $this->assertSame('Comprobante de pago por revisar', $customer->ia_pause_reason);
    }
}
