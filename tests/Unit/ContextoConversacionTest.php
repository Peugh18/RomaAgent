<?php

namespace Tests\Unit;

use App\Models\CompanySetting;
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

        $prompt = (new ContextoConversacion(new ConfiguracionEmpresa()))->construirPromptCompleto();
        $promptAgente = (new ContextoConversacion(new ConfiguracionEmpresa()))->construirPromptParaAgente();

        $this->assertStringContainsString('Número de DNI', $prompt);
        $this->assertStringContainsString('Sede exacta de shalom', $prompt);
        $this->assertStringContainsString('enviar_foto_producto', $promptAgente);
        $this->assertStringNotContainsString('Plantillas a configurar', $prompt);
    }
}
