<?php

namespace App\Console\Commands;

use App\Models\AgenteConfig;
use App\Models\CompanySetting;
use App\Models\EmpresaInfoConfig;
use App\Models\HorarioConfig;
use App\Models\MensajeConfig;
use App\Models\VentaConfig;
use Illuminate\Console\Command;

/**
 * Comando para migrar datos de CompanySetting (monolítico)
 * a las nuevas tablas especializadas
 *
 * USO:
 *   php artisan config:migrar --dry-run    (ver qué se migraría)
 *   php artisan config:migrar --execute    (ejecutar migración)
 */
class MigrarConfiguracionesCommand extends Command
{
    protected $signature = 'config:migrar
                            {--dry-run : Mostrar qué se migraría sin ejecutar}
                            {--execute : Ejecutar la migración real}
                            {--force : Forzar sin confirmación}';

    protected $description = 'Migrar configuraciones de CompanySetting monolítico a tablas especializadas';

    public function handle(): int
    {
        if (! $this->option('dry-run') && ! $this->option('execute')) {
            $this->error('Debes especificar --dry-run o --execute');
            $this->line('');
            $this->line('Ejemplos:');
            $this->line('  php artisan config:migrar --dry-run   # Ver preview');
            $this->line('  php artisan config:migrar --execute   # Ejecutar migración');

            return self::FAILURE;
        }

        $companySettings = CompanySetting::all();

        if ($companySettings->isEmpty()) {
            $this->warn('No hay CompanySettings para migrar');

            return self::SUCCESS;
        }

        $this->info("Encontradas {$companySettings->count()} configuraciones para migrar");
        $this->line('');

        foreach ($companySettings as $config) {
            $this->migrarConfiguracion($config);
        }

        if ($this->option('dry-run')) {
            $this->line('');
            $this->info('✓ Esto fue un dry-run. No se modificaron datos.');
            $this->line('  Ejecuta con --execute para migrar realmente.');
        } else {
            $this->line('');
            $this->info('✓ Migración completada exitosamente');
        }

        return self::SUCCESS;
    }

    private function migrarConfiguracion(CompanySetting $config): void
    {
        $this->info("Procesando CompanySetting ID: {$config->id}");

        // 1. Migrar EmpresaInfo
        $empresaData = $this->extraerDatosEmpresa($config);
        $this->mostrarDato('EmpresaInfo', $empresaData);

        if ($this->option('execute')) {
            EmpresaInfoConfig::updateOrCreate(
                ['company_setting_id' => $config->id],
                $empresaData
            );
        }

        // 2. Migrar AgenteConfig
        $agenteData = $this->extraerDatosAgente($config);
        $this->mostrarDato('AgenteConfig', $agenteData);

        if ($this->option('execute')) {
            AgenteConfig::updateOrCreate(
                ['company_setting_id' => $config->id],
                $agenteData
            );
        }

        // 3. Migrar MensajeConfig
        $mensajeData = $this->extraerDatosMensajes($config);
        $this->mostrarDato('MensajeConfig', $mensajeData);

        if ($this->option('execute')) {
            MensajeConfig::updateOrCreate(
                ['company_setting_id' => $config->id],
                $mensajeData
            );
        }

        // 4. Migrar VentaConfig
        $ventaData = $this->extraerDatosVentas($config);
        $this->mostrarDato('VentaConfig', $ventaData);

        if ($this->option('execute')) {
            VentaConfig::updateOrCreate(
                ['company_setting_id' => $config->id],
                $ventaData
            );
        }

        // 5. Migrar HorarioConfig
        $horarioData = $this->extraerDatosHorarios($config);
        $this->mostrarDato('HorarioConfig', $horarioData);

        if ($this->option('execute')) {
            HorarioConfig::updateOrCreate(
                ['company_setting_id' => $config->id],
                $horarioData
            );
        }

        $this->line('');
    }

    private function extraerDatosEmpresa(CompanySetting $config): array
    {
        return [
            'company_name' => $config->company_name ?? 'Empresa sin nombre',
            'ruc' => $config->ruc,
            'razon_social' => $config->razon_social,
            'celular' => $config->celular,
            'email' => $config->email,
            'website' => $config->website,
            'logo_path' => $config->logo_path,
            'actividad_economica' => $config->actividad_economica,
            'informacion_adicional' => $config->informacion_adicional,
            'social_networks' => $config->social_networks,
            'address' => $config->address,
        ];
    }

    private function extraerDatosAgente(CompanySetting $config): array
    {
        return [
            'activado' => $config->agente_ia_activado ?? false,
            'modelo' => $config->agente_ia_modelo ?? 'gemini-3.1-flash-lite',
            'api_key_encrypted' => $config->agente_ia_api_key_encrypted,
            'temperatura' => $config->agente_ia_temperatura ?? 0.70,
            'tono_bot' => $config->tono_bot,
            'estilo_comunicacion' => $config->estilo_comunicacion,
            'personalidad_bot' => $config->personalidad_bot,
            'respuesta_si_es_bot' => $config->respuesta_si_es_bot,
        ];
    }

    private function extraerDatosMensajes(CompanySetting $config): array
    {
        return [
            'saludo_inicial' => $config->saludo_inicial,
            'reglas_comunicacion' => $config->reglas_comunicacion,
            'flujo_ventas' => $config->flujo_ventas,
            'recordatorio_3min' => $config->mensaje_recordatorio_3min,
            'recordatorio_15min' => $config->mensaje_recordatorio_15min,
            'recordatorio_datos' => $config->mensaje_recordatorio_datos,
            'pedido_confirmado' => $config->mensaje_pedido_confirmado,
            'pedido_enviado' => $config->mensaje_pedido_enviado,
            'pedido_entregado' => $config->mensaje_pedido_entregado,
            'comprobante_recibido' => $config->mensaje_comprobante_recibido,
            'comprobante_fuera_horario' => $config->mensaje_comprobante_fuera_horario,
            'espera_link_tarjeta' => $config->mensaje_espera_link_tarjeta,
        ];
    }

    private function extraerDatosVentas(CompanySetting $config): array
    {
        return [
            'moneda' => $config->moneda ?? 'PEN',
            'metodos_pago' => $config->metodos_pago,
            'comision_tarjeta' => $config->comision_tarjeta ?? 0,
            'formato_registro_venta' => $config->formato_registro_venta ?? 'formato_simple',
            'protocolo_traspaso' => $config->protocolo_traspaso,
        ];
    }

    private function extraerDatosHorarios(CompanySetting $config): array
    {
        return [
            'horario_atencion' => $config->horario_atencion,
            'horario_entregas' => $config->horario_entregas,
            'horario_shalom' => $config->horario_shalom,
            'politica_devoluciones' => $config->politica_devoluciones,
            'restricciones_especiales' => $config->restricciones_especiales,
            'plantillas_datos' => $config->plantillas_datos,
            'standard_size' => $config->standard_size,
        ];
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function mostrarDato(string $tabla, array $datos): void
    {
        $noVacios = collect($datos)->filter(fn ($v) => ! empty($v))->count();
        $total = count($datos);

        $this->line("  → {$tabla}: {$noVacios}/{$total} campos con datos");

        if ($this->output->isVerbose()) {
            foreach ($datos as $key => $value) {
                $preview = is_array($value) ? json_encode($value) : (string) $value;
                $preview = substr($preview, 0, 50);
                if (! empty($preview)) {
                    $this->line("     - {$key}: {$preview}");
                }
            }
        }
    }
}
