<?php



namespace App\Http\Controllers\Api;



use App\Actions\RestablecerConfiguracionEmpresa;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCompanySettingRequest;
use App\Models\CompanySetting;
use App\Services\ConfiguracionAgente;
use App\Services\ConfiguracionEmpresa;
use App\Support\NormalizadorStockTallas;
use App\Support\PlantillasDatosEmpresa;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;



class CompanySettingController extends Controller

{

    public function index(): JsonResponse
    {
        CompanySetting::query()->firstOrCreate([]);

        return response()->json($this->buildSettingsResponse(new ConfiguracionEmpresa()));
    }



    public function update(StoreCompanySettingRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $settings = CompanySetting::firstOrCreate([]);

        if (isset($validated['standard_size'])) {
            $validated['standard_size'] = strtoupper(trim($validated['standard_size'])) ?: NormalizadorStockTallas::DEFAULT_SIZE_KEY;
        }

        if (array_key_exists('plantillas_datos', $validated)) {
            $validated['plantillas_datos'] = PlantillasDatosEmpresa::normalizar($validated['plantillas_datos']);
        }

        $datosIA = [
            'agente_ia_activado' => $validated['agente_ia_activado'] ?? null,
            'agente_ia_modelo' => $validated['agente_ia_modelo'] ?? null,
            'agente_ia_temperatura' => $validated['agente_ia_temperatura'] ?? null,
        ];

        if (! empty($validated['agente_ia_api_key'])) {
            $datosIA['agente_ia_api_key'] = $validated['agente_ia_api_key'];
        }

        $datosIA = array_filter($datosIA, fn ($value) => $value !== null);

        if (! empty($datosIA)) {
            (new ConfiguracionAgente())->actualizarConfiguracion($datosIA);
        }

        $camposEmpresa = array_diff_key($validated, array_flip([
            'agente_ia_activado', 'agente_ia_modelo', 'agente_ia_api_key', 'agente_ia_temperatura',
        ]));

        $settings->update($camposEmpresa);

        Cache::forget('contexto_prompt_completo_'.$settings->id);

        return response()->json($this->buildSettingsResponse(new ConfiguracionEmpresa()));
    }

    public function destroy(RestablecerConfiguracionEmpresa $restablecer): JsonResponse
    {
        $restablecer->handle();

        return response()->json($this->buildSettingsResponse(new ConfiguracionEmpresa()));

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

            'prompt_preview' => $datos['prompt_preview'],

            'prompt_completo' => $datos['prompt_completo'],

        ];

    }

}


