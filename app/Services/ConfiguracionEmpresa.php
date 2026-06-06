<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\Product;
use App\Support\NormalizadorStockTallas;
use App\Support\PlantillasDatosEmpresa;

class ConfiguracionEmpresa
{
    private CompanySetting $config;

    public function __construct()
    {
        $this->config = CompanySetting::first() ?? new CompanySetting();
    }

    /**
     * Obtiene todos los datos de configuración de empresa.
     */
    public function obtenerTodos(): array
    {
        $contexto = new ContextoConversacion($this);
        $promptCompleto = $contexto->construirPromptParaAgente();

        return [
            'empresa' => $this->obtenerDatosEmpresa(),
            'actividad' => $this->obtenerActividadParaPrompt(),
            'personalidad' => $this->obtenerPersonalidad(),
            'moneda' => $this->obtenerMoneda(),
            'metodos_pago' => $this->obtenerMetodosPago(),
            'informacion_extra' => $this->obtenerInformacionExtra(),
            'ia' => (new ConfiguracionAgente())->obtenerDatosCompletos(),
            'estadisticas' => $this->obtenerEstadisticas($promptCompleto),
            'prompt_preview' => substr($promptCompleto, 0, 1000),
            'prompt_completo' => $promptCompleto,
        ];
    }

    /**
     * Obtiene estadísticas de la configuración.
     */
    public function obtenerEstadisticas(?string $promptCompleto = null): array
    {
        $productosActivos = Product::where('status', Product::ESTADO_DISPONIBLE)->count();
        $zonasDelivery = \App\Models\DeliveryZone::count();
        $metodosActivos = count($this->obtenerMetodosPago());

        $completitud = $this->getPorcentajeCompletitud();
        $camposFaltantes = $this->obtenerCamposFaltantes();

        $promptCompleto ??= (new ContextoConversacion($this))->construirPromptParaAgente();
        $tokensEstimados = $this->estimarTokens($promptCompleto);

        $advertencias = [];
        if ($productosActivos === 0) {
            $advertencias[] = 'No hay productos disponibles en el catálogo';
        }

        return [
            'completitud' => $completitud,
            'campos_faltantes' => $camposFaltantes,
            'productos_activos' => $productosActivos,
            'zonas_delivery' => $zonasDelivery,
            'metodos_pago_count' => $metodosActivos,
            'tokens_estimados' => $tokensEstimados,
            'esta_lista' => $camposFaltantes === [] && $metodosActivos > 0,
            'advertencias' => $advertencias,
        ];
    }

    /**
     * @return array<string, callable(): bool>
     */
    private function definicionCamposConfiguracion(): array
    {
        return [
            'Nombre de empresa' => fn (): bool => ! empty($this->config->company_name),
            'Celular' => fn (): bool => ! empty($this->config->celular),
            'Email' => fn (): bool => ! empty($this->config->email),
            'Actividad económica' => fn (): bool => ! empty($this->config->actividad_economica),
            'Personalidad del bot' => fn (): bool => ! empty($this->config->personalidad_bot),
            'Saludo inicial' => fn (): bool => ! empty($this->config->saludo_inicial),
            'Reglas de comunicación' => fn (): bool => ! empty($this->config->reglas_comunicacion),
            'Flujo de ventas' => fn (): bool => ! empty($this->config->flujo_ventas),
            'Métodos de pago' => fn (): bool => ! empty($this->config->metodos_pago),
            'Horario de entregas' => fn (): bool => ! empty($this->config->horario_entregas),
        ];
    }

    /**
     * Obtiene campos que faltan para completar la configuración.
     *
     * @return list<string>
     */
    private function obtenerCamposFaltantes(): array
    {
        $faltantes = [];

        foreach ($this->definicionCamposConfiguracion() as $label => $check) {
            if (! $check()) {
                $faltantes[] = $label;
            }
        }

        return $faltantes;
    }

    /**
     * Estima tokens del prompt (aproximado: 1 token ≈ 4 caracteres).
     */
    private function estimarTokens(string $texto): array
    {
        $caracteres = strlen($texto);
        $tokensEstimados = ceil($caracteres / 4);

        return [
            'entrada' => $tokensEstimados,
            'salida' => 1000, // Estimado
            'total' => $tokensEstimados + 1000,
        ];
    }

    /**
     * Datos de la empresa.
     */
    public function obtenerDatosEmpresa(): array
    {
        return [
            'nombre' => $this->config->company_name ?? '',
            'ruc' => $this->config->ruc,
            'razon_social' => $this->config->razon_social,
            'celular' => $this->config->celular,
            'email' => $this->config->email,
            'website' => $this->config->website,
            'logo_path' => $this->config->logo_path,
            'direccion' => $this->config->address,
            'standard_size' => $this->config->standard_size ?? NormalizadorStockTallas::defaultSizeKey(),
            'social_networks' => $this->obtenerRedesSociales(),
        ];
    }

    /**
     * @return array{instagram: string, facebook: string, tiktok: string}
     */
    public function obtenerRedesSociales(): array
    {
        $redes = $this->config->social_networks ?? [];

        if (! is_array($redes)) {
            $redes = [];
        }

        return [
            'instagram' => (string) ($redes['instagram'] ?? ''),
            'facebook' => (string) ($redes['facebook'] ?? ''),
            'tiktok' => (string) ($redes['tiktok'] ?? ''),
        ];
    }

    /**
     * Actividad económica.
     */
    public function obtenerActividad(): string
    {
        return trim((string) ($this->config->actividad_economica ?? ''));
    }

    public function obtenerActividadParaPrompt(): string
    {
        $actividad = $this->obtenerActividad();

        return $actividad !== '' ? $actividad : 'Comercio';
    }

    /**
     * Personalidad del bot.
     */
    public function obtenerPersonalidad(): array
    {
        return [
            'tono' => $this->config->tono_bot ?? 'cálido y cercano',
            'estilo' => $this->config->estilo_comunicacion ?? 'natural',
            'descripcion' => $this->config->personalidad_bot ?? '',
            'respuesta_si_es_bot' => $this->config->respuesta_si_es_bot ?? '',
        ];
    }

    /**
     * Moneda configurada.
     */
    public function obtenerMoneda(): string
    {
        return $this->config->moneda ?? 'PEN';
    }

    /**
     * Métodos de pago configurados.
     */
    public function obtenerMetodosPago(): array
    {
        $metodos = $this->config->metodos_pago ?? [];

        return is_array($metodos) ? $metodos : [];
    }

    /**
     * Información extra (horarios, políticas, etc).
     */
    public function obtenerInformacionExtra(): array
    {
        return [
            'horario_atencion' => $this->config->horario_atencion,
            'politica_devoluciones' => $this->config->politica_devoluciones,
            'restricciones_especiales' => $this->config->restricciones_especiales,
            'informacion_adicional' => $this->config->informacion_adicional,
        ];
    }

    public function obtenerConfiguracionRomaStore(): array
    {
        $zonas = \App\Models\DeliveryZone::all();
        $tarifarioMotorizado = [];
        $tarifarioShalom = [];

        foreach ($zonas as $zona) {
            if ((float) $zona->cost_motorizado > 0) {
                $tarifarioMotorizado[$zona->district] = $zona->cost_motorizado;
            }

            $tarifarioShalom[$zona->district] = $zona->cost_shalom;
        }

        return [
            'saludo_inicial' => $this->config->saludo_inicial ?? '',
            'reglas_comunicacion' => $this->config->reglas_comunicacion ?? '',
            'flujo_ventas' => $this->config->flujo_ventas ?? '',
            'plantillas_datos' => PlantillasDatosEmpresa::normalizar($this->config->plantillas_datos),
            'horario_entregas' => $this->config->horario_entregas ?? '',
            'horario_shalom' => $this->config->horario_shalom ?? '',
            'protocolo_traspaso' => $this->config->protocolo_traspaso ?? '',
            'formato_registro_venta' => $this->config->formato_registro_venta ?? '',
            'confirmacion_pago' => [
                'mensaje_comprobante_recibido' => $this->config->mensaje_comprobante_recibido ?? '',
                'mensaje_comprobante_fuera_horario' => $this->config->mensaje_comprobante_fuera_horario ?? '',
                'mensaje_pedido_confirmado' => $this->config->mensaje_pedido_confirmado ?? '',
                'mensaje_espera_link_tarjeta' => $this->config->mensaje_espera_link_tarjeta ?? '',
            ],
            'recordatorios' => [
                '3min' => $this->config->mensaje_recordatorio_3min ?? '',
                '15min' => $this->config->mensaje_recordatorio_15min ?? '',
                'datos' => $this->config->mensaje_recordatorio_datos ?? '',
            ],
            'pagos' => [
                'tarjeta' => [
                    'comision' => $this->config->comision_tarjeta,
                ],
            ],
            'entregas' => [
                'motorizado' => $tarifarioMotorizado,
                'shalom' => $tarifarioShalom,
            ],
        ];
    }

    /**
     * Obtiene datos para construir el prompt.
     */
    public function obtenerContextoParaPrompt(): array
    {
        $extra = $this->obtenerInformacionExtra();

        return [
            'actividad' => $this->obtenerActividadParaPrompt(),
            'moneda' => $this->obtenerMoneda(),
            'horario' => $extra['horario_atencion'],
            'politica_devoluciones' => $extra['politica_devoluciones'],
            'restricciones' => $extra['restricciones_especiales'],
            'info_extra' => $extra['informacion_adicional'],
        ];
    }

    /**
     * Obtiene porcentaje de completitud de la configuración.
     */
    public function getPorcentajeCompletitud(): int
    {
        $campos = $this->definicionCamposConfiguracion();
        $total = count($campos);

        if ($total === 0) {
            return 0;
        }

        $completados = count(array_filter($campos, fn (callable $check): bool => $check()));

        return (int) round(($completados / $total) * 100);
    }
}
