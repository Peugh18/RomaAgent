<?php

namespace Tests\Unit;

use App\Models\CompanySetting;
use App\Models\Message;
use App\Services\ConfiguracionEmpresa;
use App\Services\ContextoConversacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContextoConversacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_prompt_includes_default_data_templates_when_not_configured(): void
    {
        CompanySetting::factory()->create([
            'plantillas_datos' => null,
            'flujo_ventas' => 'Flujo de prueba',
            'saludo_inicial' => 'Hola hermosa',
        ]);

        $prompt = (new ContextoConversacion(new ConfiguracionEmpresa))->construirPromptCompleto();
        $promptAgente = (new ContextoConversacion(new ConfiguracionEmpresa))->construirPromptParaAgente();

        $this->assertStringContainsString('Número de DNI', $prompt);
        $this->assertStringContainsString('Sede exacta de shalom', $prompt);
        $this->assertStringContainsString('enviar_foto_producto', $promptAgente);
        $this->assertStringNotContainsString('Plantillas a configurar', $prompt);
    }

    public function test_historial_usa_transcripcion_de_audios(): void
    {
        Message::factory()->create([
            'phone_number' => '51999999999',
            'direction' => 'incoming',
            'content' => '🎤 Audio',
            'metadata' => [
                'type' => 'audio',
                'transcript' => 'Quiero el color naranja',
            ],
        ]);

        $historial = (new ContextoConversacion(new ConfiguracionEmpresa))->obtenerHistorial('51999999999', 5);

        $this->assertSame('(audio) Quiero el color naranja', $historial[0]['content']);
    }

    public function test_prompt_indica_no_repetir_saludo_si_hay_historial(): void
    {
        CompanySetting::factory()->create([
            'saludo_inicial' => 'Hola hermosa',
        ]);

        $prompt = (new ContextoConversacion(new ConfiguracionEmpresa))->construirPromptCompleto();

        $this->assertStringContainsString('NO repitas este saludo de bienvenida', $prompt);
        $this->assertStringContainsString('AUDIOS DE LA CLIENTA', $prompt);
    }
}
