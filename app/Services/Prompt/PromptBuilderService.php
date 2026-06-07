<?php

namespace App\Services\Prompt;

use App\Models\CompanySetting;
use App\Services\ConfiguracionEmpresa;
use App\Support\FormateadorCatalogoProductos;
use Illuminate\Support\Facades\Cache;

/**
 * Builder de Prompts con Caché Granular
 *
 * CONCEPTO CLAVE:
 * - Datos: Separados en tablas (AgenteConfig, MensajeConfig, etc.)
 * - Caché: Por secciones (invalidar solo lo que cambia)
 * - Prompt Final: UNO SOLO, unificado para Gemini
 *
 * Beneficios:
 * 1. Si cambia el teléfono de empresa → Solo invalida sección Empresa
 * 2. Si cambia el modelo IA → Solo invalida sección Agente
 * 3. El prompt que va a Gemini sigue siendo completo y unificado
 */
class PromptBuilderService
{
    private CompanySetting $config;

    public function __construct(
        private ConfiguracionEmpresa $configuracion,
    ) {}

    /**
     * Construye el prompt COMPLETO y UNIFICADO para Gemini
     *
     * Este es el método principal - devuelve UN string con todo el contexto
     *
     * NOTA: Usa caché simple (sin tags) para compatibilidad con cualquier driver
     * (database, file, array). La invalidación se hace manual via observers.
     */
    public function construirPromptUnificado(): string
    {
        $this->config = CompanySetting::first() ?? new CompanySetting;
        $configId = $this->config->id ?? 0;

        // Cache del prompt COMPLETO
        // Compatible con cualquier driver: database, file, array, redis
        return Cache::remember(
            "prompt_unificado_v2_{$configId}",
            300, // 5 minutos
            fn (): string => $this->ensamblarPromptDesdeSecciones()
        );
    }

    /**
     * Ensambla el prompt final desde secciones cacheadas individualmente
     *
     * Cada sección se cachea por separado - si una falla, las otras sirven
     */
    private function ensamblarPromptDesdeSecciones(): string
    {
        $configId = $this->config->id ?? 0;

        $secciones = [
            // Sección 1: Contexto de Empresa (cache separada)
            $this->obtenerSeccionEmpresa($configId),

            // Sección 2: Personalidad del Agente (cache separada)
            $this->obtenerSeccionAgente($configId),

            // Sección 3: Reglas y Mensajes (cache separada)
            $this->obtenerSeccionMensajes($configId),

            // Sección 4: Configuración de Ventas (cache separada)
            $this->obtenerSeccionVentas($configId),

            // Sección 5: Horarios y Políticas (cache separada)
            $this->obtenerSeccionHorarios($configId),

            // Sección 6: Catálogo Dinámico (siempre fresco)
            $this->construirSeccionCatalogo(),
        ];

        // UNIFICAR en un solo prompt con separadores claros
        return implode("\n\n".str_repeat('=', 60)."\n\n", array_filter($secciones));
    }

    /**
     * Sección 1: Datos de Empresa (cache independiente)
     */
    private function obtenerSeccionEmpresa(int $configId): string
    {
        return Cache::remember(
            "seccion_empresa_{$configId}",
            600,
            function (): string {
                $empresa = $this->config->obtenerOCrearEmpresaInfo();

                return <<<EMPRESA
# DATOS DE LA EMPRESA

**Nombre:** {$empresa->company_name}
**RUC:** {$empresa->ruc}
**Razón Social:** {$empresa->razon_social}
**Celular/WhatsApp:** {$empresa->celular}
**Email:** {$empresa->email}
**Website:** {$empresa->website}
**Dirección:** {$empresa->address}

**Actividad Económica:**
{$empresa->actividad_economica}

**Información Adicional:**
{$empresa->informacion_adicional}

**Redes Sociales:**
{$this->formatearRedesSociales($empresa->social_networks ?? [])}
EMPRESA;
            }
        );
    }

    /**
     * Sección 2: Configuración del Agente IA (cache independiente)
     */
    private function obtenerSeccionAgente(int $configId): string
    {
        return Cache::remember(
            "seccion_agente_{$configId}",
            600,
            function (): string {
                $agente = $this->config->obtenerOCrearAgente();

                $estado = $agente->activado ? 'ACTIVADO' : 'PAUSADO';
                $modelo = $agente->modelo;
                $temperatura = $agente->temperatura;

                return <<<AGENTE
# CONFIGURACIÓN DEL AGENTE VENDEDOR

**Estado:** {$estado}
**Modelo IA:** {$modelo}
**Temperatura:** {$temperatura}

**Tono de Comunicación:**
{$agente->tono_bot}

**Estilo de Comunicación:**
{$agente->estilo_comunicacion}

**Personalidad del Bot:**
{$agente->personalidad_bot}

**Respuesta cuando detecta que es un Bot:**
{$agente->respuesta_si_es_bot}
AGENTE;
            }
        );
    }

    /**
     * Sección 3: Mensajes y Plantillas (cache independiente)
     */
    private function obtenerSeccionMensajes(int $configId): string
    {
        return Cache::remember(
            "seccion_mensajes_{$configId}",
            600,
            function (): string {
                $mensajes = $this->config->obtenerOCrearMensajes();

                return <<<MENSAJES
# MENSAJES Y PLANTILLAS

## Saludo Inicial
{$mensajes->saludo_inicial}

## Reglas de Comunicación
{$mensajes->reglas_comunicacion}

## Flujo de Ventas
{$mensajes->flujo_ventas}

## Recordatorios Automáticos

**Recordatorio a los 3 min:**
{$mensajes->recordatorio_3min}

**Recordatorio a los 15 min:**
{$mensajes->recordatorio_15min}

**Recordatorio de datos faltantes:**
{$mensajes->recordatorio_datos}

## Mensajes de Pedido

**Pedido Confirmado:**
{$mensajes->pedido_confirmado}

**Pedido Enviado:**
{$mensajes->pedido_enviado}

**Pedido Entregado:**
{$mensajes->pedido_entregado}

## Mensajes de Comprobante

**Comprobante Recibido:**
{$mensajes->comprobante_recibido}

**Comprobante Fuera Horario:**
{$mensajes->comprobante_fuera_horario}

**Esperando Link de Tarjeta:**
{$mensajes->espera_link_tarjeta}
MENSAJES;
            }
        );
    }

    /**
     * Sección 4: Configuración de Ventas (cache independiente)
     */
    private function obtenerSeccionVentas(int $configId): string
    {
        return Cache::remember(
            "seccion_ventas_{$configId}",
            600,
            function (): string {
                $ventas = $this->config->obtenerOCrearVentas();

                $metodos = $this->formatearMetodosPago($ventas->metodos_pago ?? []);
                $comision = $ventas->comision_tarjeta;
                $moneda = $ventas->moneda;
                $simbolo = $ventas->simboloMoneda();

                return <<<VENTAS
# CONFIGURACIÓN DE VENTAS

**Moneda:** {$moneda} ({$simbolo})
**Comisión Tarjeta:** {$comision}%
**Formato de Registro:** {$ventas->formato_registro_venta}

## Métodos de Pago Aceptados
{$metodos}

## Protocolo de Traspaso
{$ventas->protocolo_traspaso}
VENTAS;
            }
        );
    }

    /**
     * Sección 5: Horarios y Políticas (cache independiente)
     */
    private function obtenerSeccionHorarios(int $configId): string
    {
        return Cache::remember(
            "seccion_horarios_{$configId}",
            600,
            function (): string {
                $horarios = $this->config->obtenerOCrearHorarios();

                return <<<HORARIOS
# HORARIOS Y POLÍTICAS

## Horario de Atención
{$horarios->horario_atencion}

## Horario de Entregas
{$horarios->horario_entregas}

## Horario Shalom (Agencia)
{$horarios->horario_shalom}

## Política de Devoluciones
{$horarios->politica_devoluciones}

## Restricciones Especiales
{$horarios->restricciones_especiales}

## Talla Estándar
{$horarios->standard_size}

## Plantillas de Datos Disponibles
{$this->formatearPlantillas($horarios->plantillas_datos ?? [])}
HORARIOS;
            }
        );
    }

    /**
     * Sección 6: Catálogo Dinámico (SIN CACHE - siempre actualizado)
     */
    private function construirSeccionCatalogo(): string
    {
        // Esta sección NO se cachea porque cambia frecuentemente
        // (stock, productos nuevos, precios)
        $catalogo = (new FormateadorCatalogoProductos)->generar();
        $zonas = $this->configuracion->obtenerZonasDeliveryFormateadas();

        return <<<CATALOGO
# CATÁLOGO DE PRODUCTOS Y ZONAS DE DELIVERY

## Productos Disponibles
{$catalogo}

## Zonas de Delivery
{$zonas}

---
**Nota:** Este catálogo se actualiza en tiempo real. Los precios y stock son los vigentes al momento de esta conversación.
CATALOGO;
    }

    /**
     * Instrucciones finales para el Agente
     */
    public function construirInstruccionesAgente(): string
    {
        return <<<INSTRUCCIONES
# INSTRUCCIONES PARA EL AGENTE VENDEDOR

## Rol
Eres un vendedor profesional de {$this->config->obtenerOCrearEmpresaInfo()->company_name}. Tu objetivo es ayudar a las clientas a encontrar productos, responder preguntas, y cerrar ventas de manera natural y amigable.

## Reglas Fundamentales

1. **Sé conversacional y cálido** - No uses respuestas robóticas
2. **Conoce tu catálogo** - Siempre verifica stock antes de confirmar
3. **Confirma datos** - Antes de registrar un pedido, confirma: producto, color, talla, dirección, método de pago
4. **Métodos de pago** - Solo acepta los métodos configurados arriba
5. **Horarios** - Respeta los horarios de atención y entrega
6. **Proceso de venta:**
   - Saluda amablemente
   - Entiende la necesidad
   - Muestra opciones (con fotos si es posible)
   - Confirma disponibilidad
   - Toma datos de entrega
   - Indica método de pago
   - Confirma pedido
   - Envía resumen

## Herramientas Disponibles
- `consultar_catalogo`: Buscar productos
- `crear_pedido`: Registrar nueva venta
- `actualizar_pedido`: Modificar pedido existente
- `enviar_foto_producto`: Enviar imagen de producto
- `calcular_envio`: Calcular costo de delivery

## Respuesta Ideal
- Corta (máximo 3-4 oraciones por mensaje)
- Con emojis naturales
- Pregunta para avanzar la conversación
- Siempre incluye siguiente paso sugerido
INSTRUCCIONES;
    }

    /**
     * Prompt COMPLETO: Contexto + Instrucciones
     */
    public function construirPromptCompletoParaAgente(): string
    {
        return $this->construirPromptUnificado()
            ."\n\n".str_repeat('=', 60)."\n\n"
            .$this->construirInstruccionesAgente();
    }

    /**
     * Invalidar caché de una sección específica
     *
     * NOTA: Versión compatible sin Redis (usa forget simple)
     */
    public function invalidarSeccion(string $seccion, ?int $configId = null): void
    {
        $configId ??= $this->config->id ?? 0;

        Cache::forget("seccion_{$seccion}_{$configId}");
        // También invalidar el prompt completo (se regenerará)
        Cache::forget("prompt_unificado_v2_{$configId}");
    }

    /**
     * Invalidar TODO el prompt (cuando hay cambios masivos)
     *
     * NOTA: Versión compatible sin Redis (usa forget simple)
     */
    public function invalidarTodo(?int $configId = null): void
    {
        $configId ??= $this->config->id ?? 0;

        // Invalidar todas las secciones
        Cache::forget("seccion_empresa_{$configId}");
        Cache::forget("seccion_agente_{$configId}");
        Cache::forget("seccion_mensajes_{$configId}");
        Cache::forget("seccion_ventas_{$configId}");
        Cache::forget("seccion_horarios_{$configId}");

        // Invalidar el prompt completo
        Cache::forget("prompt_unificado_v2_{$configId}");
    }

    // ============ HELPERS ============

    private function formatearRedesSociales(array $redes): string
    {
        if (empty($redes)) {
            return 'No configuradas';
        }

        $lineas = [];
        foreach ($redes as $nombre => $url) {
            $lineas[] = "- {$nombre}: {$url}";
        }

        return implode("\n", $lineas);
    }

    private function formatearMetodosPago(array $metodos): string
    {
        if (empty($metodos)) {
            return '- No hay métodos configurados';
        }

        $lineas = [];
        foreach ($metodos as $metodo) {
            $lineas[] = "- {$metodo}";
        }

        return implode("\n", $lineas);
    }

    private function formatearPlantillas(array $plantillas): string
    {
        if (empty($plantillas)) {
            return 'No hay plantillas configuradas';
        }

        return json_encode($plantillas, JSON_PRETTY_PRINT);
    }
}
