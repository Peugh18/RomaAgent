<?php

namespace App\Services;

use App\Models\AgenteConfig;
use App\Models\CompanySetting;
use App\Models\DeliveryZone;
use App\Models\EmpresaInfoConfig;
use App\Models\HorarioConfig;
use App\Models\MensajeConfig;
use App\Models\Product;
use App\Models\VentaConfig;
use App\Support\NormalizadorStockTallas;
use App\Support\PlantillasDatosEmpresa;

/**
 * Servicio de configuración de empresa
 *
 * REFACTORIZADO: Ahora lee de tablas especializadas vía relaciones:
 * - empresaInfo: Datos básicos de empresa (EmpresaInfoConfig)
 * - agente: Configuración IA (AgenteConfig)
 * - mensajes: Plantillas (MensajeConfig)
 * - ventas: Configuración de ventas (VentaConfig)
 * - horarios: Horarios y políticas (HorarioConfig)
 *
 * ANTES: Leía 58 campos directamente de CompanySetting
 */
class ConfiguracionEmpresa
{
    private CompanySetting $companySetting;

    private ?EmpresaInfoConfig $empresaInfo = null;

    private ?AgenteConfig $agente = null;

    private ?MensajeConfig $mensajes = null;

    private ?VentaConfig $ventas = null;

    private ?HorarioConfig $horarios = null;

    public function __construct()
    {
        $this->companySetting = CompanySetting::first() ?? new CompanySetting;

        // Cargar relaciones si existen
        if ($this->companySetting->exists) {
            $this->empresaInfo = $this->companySetting->empresaInfo;
            $this->agente = $this->companySetting->agente;
            $this->mensajes = $this->companySetting->mensajes;
            $this->ventas = $this->companySetting->ventas;
            $this->horarios = $this->companySetting->horarios;
        }
    }

    /**
     * Obtiene todos los datos de configuración de empresa.
     */
    public function obtenerTodos(): array
    {
        $contexto = new ContextoConversacion($this);
        $promptSecciones = $contexto->construirPromptSecciones();

        return [
            'empresa' => $this->obtenerDatosEmpresa(),
            'actividad' => $this->obtenerActividadParaPrompt(),
            'personalidad' => $this->obtenerPersonalidad(),
            'moneda' => $this->obtenerMoneda(),
            'metodos_pago' => $this->obtenerMetodosPago(),
            'informacion_extra' => $this->obtenerInformacionExtra(),
            'ia' => (new ConfiguracionAgente)->obtenerDatosCompletos(),
            'estadisticas' => $this->obtenerEstadisticas($promptSecciones['completo']),
            'prompt_completo' => $promptSecciones['completo'],
            'prompt_secciones' => [
                'sistema' => $promptSecciones['sistema'],
                'configuracion' => $promptSecciones['configuracion'],
                'completo' => $promptSecciones['completo'],
            ],
        ];
    }

    /**
     * Obtiene estadísticas de la configuración.
     */
    public function obtenerEstadisticas(?string $promptCompleto = null): array
    {
        $productosActivos = Product::where('status', Product::ESTADO_DISPONIBLE)->count();
        $zonasDelivery = DeliveryZone::count();
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
            'Nombre de empresa' => fn (): bool => ! empty($this->empresaInfo?->company_name),
            'Actividad económica' => fn (): bool => ! empty($this->empresaInfo?->actividad_economica),
            'Personalidad del bot' => fn (): bool => ! empty($this->agente?->personalidad_bot),
            'Saludo inicial' => fn (): bool => ! empty($this->mensajes?->saludo_inicial),
            'Reglas de comunicación' => fn (): bool => ! empty($this->mensajes?->reglas_comunicacion),
            'Métodos de pago' => fn (): bool => ! empty($this->ventas?->metodos_pago),
            'Horario de entregas' => fn (): bool => ! empty($this->horarios?->horario_entregas),
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
            'nombre' => $this->empresaInfo?->company_name ?? '',
            'ruc' => $this->empresaInfo?->ruc,
            'razon_social' => $this->empresaInfo?->razon_social,
            'logo_path' => $this->empresaInfo?->logo_path,
            'direccion' => $this->empresaInfo?->address,
            'standard_size' => $this->horarios?->standard_size ?? NormalizadorStockTallas::defaultSizeKey(),
            'social_networks' => $this->obtenerRedesSociales(),
        ];
    }

    /**
     * @return array{instagram: string, facebook: string, tiktok: string}
     */
    public function obtenerRedesSociales(): array
    {
        $redes = $this->empresaInfo?->social_networks ?? [];

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
        return trim((string) ($this->empresaInfo?->actividad_economica ?? ''));
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
            'tono' => $this->agente?->tono_bot ?? 'cálido y cercano',
            'estilo' => $this->agente?->estilo_comunicacion ?? 'natural',
            'descripcion' => $this->agente?->personalidad_bot ?? '',
            'respuesta_si_es_bot' => $this->agente?->respuesta_si_es_bot ?? '',
        ];
    }

    /**
     * Moneda configurada.
     */
    public function obtenerMoneda(): string
    {
        return $this->ventas?->moneda ?? 'PEN';
    }

    /**
     * Métodos de pago configurados.
     */
    public function obtenerMetodosPago(): array
    {
        $metodos = $this->ventas?->metodos_pago ?? [];

        return is_array($metodos) ? $metodos : [];
    }

    /**
     * Información extra (horarios, políticas, etc).
     */
    public function obtenerInformacionExtra(): array
    {
        return [
            'horario_atencion' => $this->horarios?->horario_atencion,
            'politica_devoluciones' => $this->horarios?->politica_devoluciones,
            'restricciones_especiales' => $this->horarios?->restricciones_especiales,
        ];
    }

    public function obtenerConfiguracionRomaStore(): array
    {
        $zonas = DeliveryZone::all();
        $tarifarioMotorizado = [];
        $tarifarioShalom = [];

        foreach ($zonas as $zona) {
            if ((float) $zona->cost_motorizado > 0) {
                $tarifarioMotorizado[$zona->district] = $zona->cost_motorizado;
            }

            $tarifarioShalom[$zona->district] = $zona->cost_shalom;
        }

        return [
            'saludo_inicial' => $this->mensajes?->saludo_inicial ?? '',
            'reglas_comunicacion' => $this->mensajes?->reglas_comunicacion ?? '',
            'plantillas_datos' => PlantillasDatosEmpresa::normalizar($this->horarios?->plantillas_datos),
            'horario_entregas' => $this->horarios?->horario_entregas ?? '',
            'horario_shalom' => $this->horarios?->horario_shalom ?? '',
            'protocolo_traspaso' => $this->ventas?->protocolo_traspaso ?? '',
            'confirmacion_pago' => [
                'mensaje_comprobante_recibido' => $this->mensajes?->comprobante_recibido ?? '',
                'mensaje_comprobante_fuera_horario' => $this->mensajes?->comprobante_fuera_horario ?? '',
                'mensaje_pedido_confirmado' => $this->mensajes?->pedido_confirmado ?? '',
                'mensaje_pedido_enviado' => $this->mensajes?->pedido_enviado ?? '',
                'mensaje_pedido_entregado' => $this->mensajes?->pedido_entregado ?? '',
                'mensaje_espera_link_tarjeta' => $this->mensajes?->espera_link_tarjeta ?? '',
            ],
            'recordatorios' => [
                '3min' => $this->mensajes?->recordatorio_3min ?? '',
                '15min' => $this->mensajes?->recordatorio_15min ?? '',
                'datos' => $this->mensajes?->recordatorio_datos ?? '',
            ],
            'pagos' => [
                'tarjeta' => [
                    'comision' => $this->ventas?->comision_tarjeta,
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
