<?php

namespace App\Http\Controllers\Api;

use App\Actions\RestablecerConfiguracionEmpresa;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCompanySettingRequest;
use App\Models\CompanySetting;
use App\Services\ConfiguracionEmpresa;
use App\Support\NormalizadorStockTallas;
use App\Support\PlantillasDatosEmpresa;
use App\Support\SanitizadorMetodosPago;
use App\Support\ValidadorPlantillaMensaje;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

/**
 * Controller para gestión de configuraciones de empresa
 *
 * REFACTORIZADO: Ahora distribuye datos en tablas especializadas:
 * - EmpresaInfoConfig: Datos básicos de empresa
 * - AgenteConfig: Configuración de IA
 * - MensajeConfig: Plantillas de mensajes
 * - VentaConfig: Configuración de ventas
 * - HorarioConfig: Horarios y políticas
 *
 * ANTES: Todo se guardaba en CompanySetting (58 campos en 1 tabla)
 */
class CompanySettingController extends Controller
{
    public function index(): JsonResponse
    {
        $companySetting = CompanySetting::query()->firstOrCreate([]);

        // Asegurar que existan las configuraciones relacionadas
        $companySetting->obtenerOCrearEmpresaInfo();
        $companySetting->obtenerOCrearAgente();
        $companySetting->obtenerOCrearMensajes();
        $companySetting->obtenerOCrearVentas();
        $companySetting->obtenerOCrearHorarios();

        return response()->json($this->buildSettingsResponse(new ConfiguracionEmpresa));
    }

    /**
     * Actualiza configuraciones distribuyendo datos en tablas especializadas
     */
    public function update(StoreCompanySettingRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Obtener o crear CompanySetting base
        $companySetting = CompanySetting::firstOrCreate([]);
        $configId = $companySetting->id;

        // Normalizar datos especiales
        if (isset($validated['standard_size'])) {
            $validated['standard_size'] = strtoupper(trim($validated['standard_size'])) ?: NormalizadorStockTallas::DEFAULT_SIZE_KEY;
        }

        if (array_key_exists('plantillas_datos', $validated)) {
            $validated['plantillas_datos'] = PlantillasDatosEmpresa::normalizar($validated['plantillas_datos']);
        }

        if (array_key_exists('metodos_pago', $validated)) {
            $validated['metodos_pago'] = SanitizadorMetodosPago::sanitizar($validated['metodos_pago']);
        }

        // 1. ACTUALIZAR EMPRESA INFO (empresa_info_configs)
        $this->actualizarEmpresaInfo($companySetting, $validated);

        // 2. ACTUALIZAR AGENTE IA (agente_configs)
        $this->actualizarAgenteConfig($companySetting, $validated);

        // 3. ACTUALIZAR MENSAJES (mensaje_configs)
        $this->actualizarMensajeConfig($companySetting, $validated);

        // 4. ACTUALIZAR VENTAS (venta_configs)
        $this->actualizarVentaConfig($companySetting, $validated);

        // 5. ACTUALIZAR HORARIOS (horario_configs)
        $this->actualizarHorarioConfig($companySetting, $validated);

        Cache::forget('contexto_prompt_completo_'.$configId);
        Cache::forget('contexto_prompt_secciones_'.$configId);

        return response()->json($this->buildSettingsResponse(new ConfiguracionEmpresa));
    }

    /**
     * Actualiza configuración de empresa (empresa_info_configs)
     *
     * @param  array<string, mixed>  $datos
     */
    private function actualizarEmpresaInfo(CompanySetting $companySetting, array $datos): void
    {
        $empresaFields = [
            'company_name', 'vendedor_nombre', 'vendedor_genero',
            'descripcion_empresa', 'logo_path', 'actividad_economica', 'social_networks',
            'address',
        ];

        $empresaData = array_intersect_key($datos, array_flip($empresaFields));

        if (isset($empresaData['logo_path'])) {
            $logoPath = $empresaData['logo_path'];
            if (str_starts_with($logoPath, '/storage/')) {
                $empresaData['logo_path'] = substr($logoPath, strlen('/storage/'));
            } elseif (str_contains($logoPath, '/storage/')) {
                $parts = explode('/storage/', $logoPath);
                $empresaData['logo_path'] = end($parts);
            }
        }

        if (! empty($empresaData)) {
            $empresaInfo = $companySetting->obtenerOCrearEmpresaInfo();
            $empresaInfo->update($empresaData);
        }
    }

    /**
     * Actualiza configuración del agente IA (agente_configs)
     *
     * @param  array<string, mixed>  $datos
     */
    private function actualizarAgenteConfig(CompanySetting $companySetting, array $datos): void
    {
        $agenteFields = [
            'agente_ia_activado' => 'activado',
            'agente_ia_modelo' => 'modelo',
            'agente_ia_temperatura' => 'temperatura',
            'tono_bot' => 'tono_bot',
            'estilo_comunicacion' => 'estilo_comunicacion',
            'personalidad_bot' => 'personalidad_bot',
            'estilo_ventas' => 'estilo_ventas',
            'respuesta_si_es_bot' => 'respuesta_si_es_bot',
            'reglas_venta_criticas' => 'reglas_venta_criticas',
        ];

        $agenteData = [];
        foreach ($agenteFields as $inputKey => $dbKey) {
            if (array_key_exists($inputKey, $datos)) {
                $agenteData[$dbKey] = $datos[$inputKey];
            }
        }

        // Manejar API key por separado
        if (! empty($datos['agente_ia_api_key'])) {
            $agenteData['api_key_encrypted'] = Crypt::encryptString($datos['agente_ia_api_key']);
        }

        if (! empty($agenteData)) {
            $agenteConfig = $companySetting->obtenerOCrearAgente();
            $agenteConfig->update($agenteData);
        }
    }

    /**
     * Actualiza configuración de mensajes (mensaje_configs)
     *
     * @param  array<string, mixed>  $datos
     */
    private function actualizarMensajeConfig(CompanySetting $companySetting, array $datos): void
    {
        $mensajeFields = [
            'saludo_inicial' => 'saludo_inicial',
            'reglas_comunicacion' => 'reglas_comunicacion',
            'mensaje_recordatorio_3min' => 'recordatorio_3min',
            'mensaje_recordatorio_15min' => 'recordatorio_15min',
            'mensaje_recordatorio_datos' => 'recordatorio_datos',
            'mensaje_pedido_confirmado' => 'pedido_confirmado',
            'mensaje_pedido_enviado' => 'pedido_enviado',
            'mensaje_pedido_entregado' => 'pedido_entregado',
            'mensaje_comprobante_recibido' => 'comprobante_recibido',
            'mensaje_comprobante_fuera_horario' => 'comprobante_fuera_horario',
            'mensaje_espera_link_tarjeta' => 'espera_link_tarjeta',
        ];

        $mensajeData = [];
        foreach ($mensajeFields as $inputKey => $dbKey) {
            if (array_key_exists($inputKey, $datos)) {
                $valor = is_string($datos[$inputKey])
                    ? ValidadorPlantillaMensaje::normalizar($datos[$inputKey])
                    : $datos[$inputKey];
                $mensajeData[$dbKey] = $valor;
            }
        }

        if (! empty($mensajeData)) {
            $mensajeConfig = $companySetting->obtenerOCrearMensajes();
            $mensajeConfig->update($mensajeData);
        }
    }

    /**
     * Actualiza configuración de ventas (venta_configs)
     *
     * @param  array<string, mixed>  $datos
     */
    private function actualizarVentaConfig(CompanySetting $companySetting, array $datos): void
    {
        $ventaFields = [
            'moneda' => 'moneda',
            'metodos_pago' => 'metodos_pago',
            'comision_tarjeta' => 'comision_tarjeta',
            'protocolo_traspaso' => 'protocolo_traspaso',
        ];

        $ventaData = [];
        foreach ($ventaFields as $inputKey => $dbKey) {
            if (array_key_exists($inputKey, $datos)) {
                $ventaData[$dbKey] = $datos[$inputKey];
            }
        }

        if (! empty($ventaData)) {
            $ventaConfig = $companySetting->obtenerOCrearVentas();
            $ventaConfig->update($ventaData);
        }
    }

    /**
     * Actualiza configuración de horarios (horario_configs)
     *
     * @param  array<string, mixed>  $datos
     */
    private function actualizarHorarioConfig(CompanySetting $companySetting, array $datos): void
    {
        $horarioFields = [
            'horario_atencion' => 'horario_atencion',
            'horario_entregas' => 'horario_entregas',
            'horario_shalom' => 'horario_shalom',
            'politica_devoluciones' => 'politica_devoluciones',
            'restricciones_especiales' => 'restricciones_especiales',
            'plantillas_datos' => 'plantillas_datos',
            'standard_size' => 'standard_size',
        ];

        $horarioData = [];
        foreach ($horarioFields as $inputKey => $dbKey) {
            if (array_key_exists($inputKey, $datos)) {
                $horarioData[$dbKey] = $datos[$inputKey];
            }
        }

        if (! empty($horarioData)) {
            $horarioConfig = $companySetting->obtenerOCrearHorarios();
            $horarioConfig->update($horarioData);
        }
    }

    public function destroy(RestablecerConfiguracionEmpresa $restablecer): JsonResponse
    {
        $restablecer->handle();

        return response()->json($this->buildSettingsResponse(new ConfiguracionEmpresa));

    }

    public function promptCompleto(): JsonResponse
    {
        CompanySetting::query()->firstOrCreate([]);

        $configuracion = new ConfiguracionEmpresa;
        $datos = $configuracion->obtenerTodos();

        return response()->json([
            'prompt_completo' => $datos['prompt_completo'],
            'prompt_secciones' => $datos['prompt_secciones'],
        ]);
    }

    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate([
            'logo' => 'required|image|max:2048',
        ]);

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('logos', 'public');

            return response()->json([
                'logo_path' => Storage::url($path),
            ]);
        }

        return response()->json(['message' => 'No se pudo subir la imagen.'], 400);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSettingsResponse(ConfiguracionEmpresa $configuracionEmpresa): array
    {

        $datos = $configuracionEmpresa->obtenerTodos();

        return [

            'empresa' => $datos['empresa'],

            'standard_size' => $datos['empresa']['standard_size'] ?? NormalizadorStockTallas::DEFAULT_SIZE_KEY,

            'actividad' => $datos['actividad'],

            'personalidad' => $datos['personalidad'],

            'moneda' => $datos['moneda'],

            'metodos_pago' => $datos['metodos_pago'],

            'informacion_extra' => $datos['informacion_extra'],

            'flujo' => $configuracionEmpresa->obtenerConfiguracionRomaStore(),

            'configuracion_agente' => $datos['ia'],

            'estadisticas' => $datos['estadisticas'],

        ];

    }
}
