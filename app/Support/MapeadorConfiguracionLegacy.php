<?php

namespace App\Support;

use App\Models\CompanySetting;

/**
 * Mapea campos legacy planos (company_settings) hacia las tablas especializadas.
 */
class MapeadorConfiguracionLegacy
{
    /**
     * @return array<string, mixed>
     */
    public static function valoresPorDefecto(): array
    {
        return [
            'company_name' => null,
            'ruc' => null,
            'razon_social' => null,
            'celular' => null,
            'email' => null,
            'website' => null,
            'logo_path' => null,
            'actividad_economica' => null,
            'informacion_adicional' => null,
            'social_networks' => [
                'instagram' => '',
                'facebook' => '',
                'tiktok' => '',
            ],
            'address' => null,
            'tono_bot' => 'cálido y cercano',
            'estilo_comunicacion' => 'natural',
            'personalidad_bot' => null,
            'respuesta_si_es_bot' => null,
            'agente_ia_activado' => false,
            'agente_ia_modelo' => 'gemini-3.1-flash-lite',
            'agente_ia_api_key_encrypted' => null,
            'agente_ia_temperatura' => 0.7,
            'saludo_inicial' => null,
            'reglas_comunicacion' => null,
            'flujo_ventas' => null,
            'mensaje_recordatorio_3min' => null,
            'mensaje_recordatorio_15min' => null,
            'mensaje_recordatorio_datos' => null,
            'mensaje_comprobante_recibido' => null,
            'mensaje_comprobante_fuera_horario' => null,
            'mensaje_pedido_confirmado' => null,
            'mensaje_pedido_enviado' => null,
            'mensaje_pedido_entregado' => null,
            'mensaje_espera_link_tarjeta' => null,
            'moneda' => 'PEN',
            'metodos_pago' => [],
            'comision_tarjeta' => 5.00,
            'formato_registro_venta' => 'formato_simple',
            'protocolo_traspaso' => null,
            'horario_atencion' => null,
            'horario_entregas' => null,
            'horario_shalom' => null,
            'politica_devoluciones' => null,
            'restricciones_especiales' => null,
            'plantillas_datos' => PlantillasDatosEmpresa::porDefecto(),
            'standard_size' => 'UNICA',
        ];
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public static function aplicarDesdeArray(CompanySetting $settings, array $datos): void
    {
        if (array_key_exists('company_name', $datos)) {
            $settings->update(['company_name' => $datos['company_name']]);
        }

        $empresa = self::datosEmpresa($datos);
        if ($empresa !== []) {
            $settings->obtenerOCrearEmpresaInfo()->update($empresa);
        }

        $agente = self::datosAgente($datos);
        if ($agente !== []) {
            $settings->obtenerOCrearAgente()->update($agente);
        }

        $mensajes = self::datosMensajes($datos);
        if ($mensajes !== []) {
            $settings->obtenerOCrearMensajes()->update($mensajes);
        }

        $ventas = self::datosVentas($datos);
        if ($ventas !== []) {
            $settings->obtenerOCrearVentas()->update($ventas);
        }

        $horarios = self::datosHorarios($datos);
        if ($horarios !== []) {
            $settings->obtenerOCrearHorarios()->update($horarios);
        }
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    public static function datosEmpresa(array $datos): array
    {
        $keys = [
            'company_name', 'ruc', 'razon_social', 'celular', 'email', 'website',
            'logo_path', 'actividad_economica', 'informacion_adicional', 'social_networks', 'address',
        ];

        $empresa = array_intersect_key($datos, array_flip($keys));

        if (array_key_exists('company_name', $empresa) && $empresa['company_name'] === null) {
            $empresa['company_name'] = '';
        }

        return $empresa;
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    public static function datosAgente(array $datos): array
    {
        $mapped = [];

        if (array_key_exists('agente_ia_activado', $datos)) {
            $mapped['activado'] = (bool) $datos['agente_ia_activado'];
        }
        if (array_key_exists('agente_ia_modelo', $datos)) {
            $mapped['modelo'] = $datos['agente_ia_modelo'];
        }
        if (array_key_exists('agente_ia_api_key_encrypted', $datos)) {
            $mapped['api_key_encrypted'] = $datos['agente_ia_api_key_encrypted'];
        }
        if (array_key_exists('agente_ia_temperatura', $datos)) {
            $mapped['temperatura'] = $datos['agente_ia_temperatura'];
        }
        if (array_key_exists('tono_bot', $datos)) {
            $mapped['tono_bot'] = $datos['tono_bot'];
        }
        if (array_key_exists('estilo_comunicacion', $datos)) {
            $mapped['estilo_comunicacion'] = $datos['estilo_comunicacion'];
        }
        if (array_key_exists('personalidad_bot', $datos)) {
            $mapped['personalidad_bot'] = $datos['personalidad_bot'];
        }
        if (array_key_exists('respuesta_si_es_bot', $datos)) {
            $mapped['respuesta_si_es_bot'] = $datos['respuesta_si_es_bot'];
        }

        return $mapped;
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    public static function datosMensajes(array $datos): array
    {
        $mapped = [];

        $fieldMap = [
            'saludo_inicial' => 'saludo_inicial',
            'reglas_comunicacion' => 'reglas_comunicacion',
            'flujo_ventas' => 'flujo_ventas',
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

        foreach ($fieldMap as $legacyKey => $dbKey) {
            if (array_key_exists($legacyKey, $datos)) {
                $mapped[$dbKey] = $datos[$legacyKey];
            }
        }

        return $mapped;
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    public static function datosVentas(array $datos): array
    {
        $mapped = [];

        if (array_key_exists('moneda', $datos)) {
            $mapped['moneda'] = $datos['moneda'];
        }
        if (array_key_exists('metodos_pago', $datos)) {
            $mapped['metodos_pago'] = $datos['metodos_pago'];
        }
        if (array_key_exists('comision_tarjeta', $datos)) {
            $mapped['comision_tarjeta'] = $datos['comision_tarjeta'];
        }
        if (array_key_exists('formato_registro_venta', $datos)) {
            $mapped['formato_registro_venta'] = $datos['formato_registro_venta'];
        }
        if (array_key_exists('protocolo_traspaso', $datos)) {
            $mapped['protocolo_traspaso'] = $datos['protocolo_traspaso'];
        }

        return $mapped;
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    public static function datosHorarios(array $datos): array
    {
        $fieldMap = [
            'horario_atencion' => 'horario_atencion',
            'horario_entregas' => 'horario_entregas',
            'horario_shalom' => 'horario_shalom',
            'politica_devoluciones' => 'politica_devoluciones',
            'restricciones_especiales' => 'restricciones_especiales',
            'plantillas_datos' => 'plantillas_datos',
            'standard_size' => 'standard_size',
        ];

        $mapped = [];

        foreach ($fieldMap as $legacyKey => $dbKey) {
            if (array_key_exists($legacyKey, $datos)) {
                $mapped[$dbKey] = $datos[$legacyKey];
            }
        }

        return $mapped;
    }

    /**
     * @return list<string>
     */
    public static function clavesLegacy(): array
    {
        return array_keys(self::valoresPorDefecto());
    }
}
