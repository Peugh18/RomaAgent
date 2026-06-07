<?php

namespace Database\Factories;

use App\Models\CompanySetting;
use App\Support\MapeadorConfiguracionLegacy;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * @extends Factory<CompanySetting>
 *
 * NOTA: Refactorizado para trabajar con tablas especializadas.
 * La configuración completa se distribuye en 5 tablas relacionadas.
 */
class CompanySettingFactory extends Factory
{
    protected $model = CompanySetting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_name' => fake()->company(),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create($attributes = [], ?Model $parent = null)
    {
        $legacyKeys = array_flip(MapeadorConfiguracionLegacy::clavesLegacy());
        $legacy = array_intersect_key($attributes, $legacyKeys);
        $base = array_diff_key($attributes, $legacyKeys);

        /** @var CompanySetting $model */
        $model = parent::create($base, $parent);

        if ($legacy !== []) {
            MapeadorConfiguracionLegacy::aplicarDesdeArray($model, $legacy);
        }

        return $model->fresh();
    }

    public function configure(): static
    {
        return $this->afterCreating(function (CompanySetting $companySetting) {
            // Crear EmpresaInfoConfig con datos de prueba
            $companySetting->empresaInfo()->create([
                'company_name' => $companySetting->company_name,
                'celular' => '+51'.fake()->numerify('9########'),
                'email' => fake()->companyEmail(),
                'ruc' => fake()->numerify('20#########'),
                'razon_social' => $companySetting->company_name.' S.A.C.',
                'website' => fake()->url(),
                'actividad_economica' => 'Comercio electrónico',
                'social_networks' => [
                    'instagram' => '@'.fake()->userName(),
                    'facebook' => 'facebook.com/'.fake()->userName(),
                    'tiktok' => '@'.fake()->userName(),
                ],
            ]);

            // Crear AgenteConfig
            $companySetting->agente()->create([
                'activado' => false,
                'modelo' => 'gemini-2.5-flash',
                'temperatura' => 0.7,
                'tono_bot' => 'cálido y cercano',
                'estilo_comunicacion' => 'natural',
            ]);

            // Crear MensajeConfig
            $companySetting->mensajes()->create([
                'saludo_inicial' => '¡Hola! Bienvenida a nuestra tienda.',
                'reglas_comunicacion' => 'Sé amable, directo y profesional.',
                'flujo_ventas' => '1. Saluda 2. Entiende necesidad 3. Muestra productos 4. Cotiza 5. Confirma',
                'recordatorio_3min' => '¿Sigues ahí? Te espero para ayudarte con tu pedido.',
                'recordatorio_15min' => '¿Tienes alguna duda? Estoy aquí para ayudarte.',
            ]);

            // Crear VentaConfig
            $companySetting->ventas()->create([
                'moneda' => 'PEN',
                'metodos_pago' => [
                    ['nombre' => 'Yape', 'descripcion' => 'Pago móvil'],
                    ['nombre' => 'Plin', 'descripcion' => 'Transferencia instantánea'],
                    ['nombre' => 'Transferencia', 'descripcion' => 'BCP o Interbank'],
                ],
                'comision_tarjeta' => 5.0,
                'formato_registro_venta' => 'formato_simple',
            ]);

            // Crear HorarioConfig
            $companySetting->horarios()->create([
                'horario_atencion' => 'Lunes a Sábado 9:00 - 18:00',
                'horario_entregas' => 'Lunes a Sábado 10:00 - 20:00',
                'horario_shalom' => 'Lunes a Viernes 9:00 - 17:00',
                'politica_devoluciones' => 'Cambios hasta 7 días con ticket.',
                'standard_size' => 'UNICA',
            ]);
        });
    }

    /**
     * Estado: IA habilitada con API key
     */
    public function withIaEnabled(?string $apiKey = 'test-gemini-key'): static
    {
        return $this->afterCreating(function (CompanySetting $companySetting) use ($apiKey) {
            $agente = $companySetting->obtenerOCrearAgente();
            $agente->update([
                'activado' => true,
                'api_key_encrypted' => Crypt::encryptString($apiKey ?? 'test-gemini-key'),
            ]);
        });
    }
}
