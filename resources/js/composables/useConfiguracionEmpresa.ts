import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import type { CompanySettingsForm } from '@/types/settings';
import { mapConfiguracionAgenteDesdeApi } from '@/lib/configuracionAgente';
import { normalizarPlantillasDatos, PLANTILLAS_DATOS_DEFECTO } from '@/lib/plantillasDatos';
import { setGlobalCurrency, type CurrencyCode } from '@/composables/useCurrency';
import { invalidateStandardSizeCache } from '@/composables/useStandardSize';
import { apiJson } from '@/composables/useApi';

export function useConfiguracionEmpresa() {
    const loading = ref(false);
    const saving = ref(false);
    const resetting = ref(false);
    const error = ref<string | null>(null);
    const success = ref<string | null>(null);
    const promptCompleto = ref('');
    const promptSecciones = ref<{
        sistema: string;
        configuracion: string;
        completo: string;
    }>({ sistema: '', configuracion: '', completo: '' });

    const form = useForm<CompanySettingsForm & { estadisticas?: Record<string, unknown> }>({
        company_name: '',
        ruc: '',
        razon_social: '',
        vendedor_nombre: '',
        vendedor_genero: '',
        celular: '',
        email: '',
        website: '',
        descripcion_empresa: '',
        logo_path: '',
        actividad_economica: '',
        tono_bot: 'cálido y cercano',
        estilo_comunicacion: 'natural',
        personalidad_bot: '',
        estilo_ventas: '',
        respuesta_si_es_bot: '',
        moneda: 'PEN',
        metodos_pago: [],
        horario_atencion: '',
        politica_devoluciones: '',
        restricciones_especiales: '',
        social_networks: { instagram: '', facebook: '', tiktok: '', website: '' },
        address: '',
        standard_size: 'ESTÁNDAR',
        saludo_inicial: '',
        reglas_comunicacion: '',
        plantillas_datos: structuredClone(PLANTILLAS_DATOS_DEFECTO),
        horario_entregas: '',
        horario_shalom: '',
        protocolo_traspaso: '',
        mensaje_recordatorio_3min: '',
        mensaje_recordatorio_15min: '',
        mensaje_recordatorio_datos: '',
        comision_tarjeta: null as number | null,
        mensaje_comprobante_recibido: '',
        mensaje_comprobante_fuera_horario: '',
        mensaje_pedido_confirmado: '',
        mensaje_pedido_enviado: '',
        mensaje_pedido_entregado: '',
        mensaje_espera_link_tarjeta: '',
        configuracion_agente: {
            agente_ia_activado: false,
            agente_ia_modelo: 'gemini-2.5-flash',
            agente_ia_api_key: '',
            agente_ia_temperatura: 0.7,
            api_key_configurada: false,
            modelos_disponibles: {},
        },
        estadisticas: {},
    });

    const cargarPromptCompleto = async () => {
        try {
            const datos = await apiJson<{
                prompt_completo: string;
                prompt_secciones: { sistema: string; configuracion: string; completo: string };
            }>('/api/company-settings/prompt-completo');
            promptCompleto.value = datos.prompt_completo || '';
            promptSecciones.value = datos.prompt_secciones || { sistema: '', configuracion: '', completo: '' };
        } catch {
            promptCompleto.value = '';
            promptSecciones.value = { sistema: '', configuracion: '', completo: '' };
        }
    };

    const aplicarDatosApi = (datos: any) => {
        Object.assign(form, {
            company_name: datos.empresa?.nombre || '',
            vendedor_nombre: datos.empresa?.vendedor_nombre || '',
            vendedor_genero: datos.empresa?.vendedor_genero || '',
            celular: datos.empresa?.celular || '',
            email: datos.empresa?.email || '',
            website: datos.empresa?.website || '',
            logo_path: datos.empresa?.logo_path || '',
            address: datos.empresa?.direccion || '',
            descripcion_empresa: datos.empresa?.descripcion_empresa || '',
            social_networks: {
                instagram: datos.empresa?.social_networks?.instagram || '',
                facebook: datos.empresa?.social_networks?.facebook || '',
                tiktok: datos.empresa?.social_networks?.tiktok || '',
                website: datos.empresa?.social_networks?.website || datos.empresa?.website || '',
            },
            actividad_economica: datos.actividad && datos.actividad !== 'No especificada' ? datos.actividad : '',
            tono_bot: datos.personalidad?.tono || 'cálido y cercano',
            estilo_comunicacion: datos.personalidad?.estilo || 'natural',
            personalidad_bot: datos.personalidad?.descripcion || '',
            estilo_ventas: datos.personalidad?.estilo_ventas || '',
            respuesta_si_es_bot: datos.personalidad?.respuesta_si_es_bot || '',
            reglas_venta_criticas: datos.configuracion_agente?.reglas_venta_criticas || '',
            moneda: datos.moneda || 'PEN',
            metodos_pago: datos.metodos_pago || [],
            horario_atencion: datos.informacion_extra?.horario_atencion || '',
            politica_devoluciones: datos.informacion_extra?.politica_devoluciones || '',
            restricciones_especiales: datos.informacion_extra?.restricciones_especiales || '',
            standard_size: datos.empresa?.standard_size || datos.standard_size || 'ESTÁNDAR',
            saludo_inicial: datos.flujo?.saludo_inicial || '',
            reglas_comunicacion: datos.flujo?.reglas_comunicacion || '',
            flujo_ventas: datos.flujo?.flujo_ventas || '',
            plantillas_datos: normalizarPlantillasDatos(datos.flujo?.plantillas_datos),
            horario_entregas: datos.flujo?.horario_entregas || '',
            horario_shalom: datos.flujo?.horario_shalom || '',
            protocolo_traspaso: datos.flujo?.protocolo_traspaso || '',
            mensaje_recordatorio_3min: datos.flujo?.recordatorios?.['3min'] || '',
            mensaje_recordatorio_15min: datos.flujo?.recordatorios?.['15min'] || '',
            mensaje_recordatorio_datos: datos.flujo?.recordatorios?.datos || '',
            comision_tarjeta: datos.flujo?.pagos?.tarjeta?.comision ?? null,
            link_pago_tarjeta: datos.flujo?.pagos?.tarjeta?.link_pago || '',
            formato_registro_venta: datos.flujo?.formato_registro_venta || '',
            mensaje_comprobante_recibido: datos.flujo?.confirmacion_pago?.mensaje_comprobante_recibido || '',
            mensaje_comprobante_fuera_horario: datos.flujo?.confirmacion_pago?.mensaje_comprobante_fuera_horario || '',
            mensaje_pedido_confirmado: datos.flujo?.confirmacion_pago?.mensaje_pedido_confirmado || '',
            mensaje_pedido_enviado: datos.flujo?.confirmacion_pago?.mensaje_pedido_enviado || '',
            mensaje_pedido_entregado: datos.flujo?.confirmacion_pago?.mensaje_pedido_entregado || '',
            mensaje_espera_link_tarjeta: datos.flujo?.confirmacion_pago?.mensaje_espera_link_tarjeta || '',
            configuracion_agente: mapConfiguracionAgenteDesdeApi(datos.configuracion_agente),
            estadisticas: datos.estadisticas || {},
        });

        const moneda = (datos.moneda || 'PEN') as CurrencyCode;
        if (moneda === 'PEN' || moneda === 'USD' || moneda === 'EUR') {
            setGlobalCurrency(moneda);
        }
    };

    const cargarDatos = async () => {
        loading.value = true;
        error.value = null;

        try {
            const response = await fetch('/api/company-settings', {
                credentials: 'include',
                headers: { Accept: 'application/json' },
            });

            const contentType = response.headers.get('content-type');
            if (!contentType?.includes('application/json')) {
                throw new Error('Error del servidor: respuesta no válida');
            }

            const datos = await response.json();

            if (!response.ok) {
                throw new Error(
                    (typeof datos?.message === 'string' && datos.message)
                        || `Error al cargar configuración (${response.status})`,
                );
            }

            aplicarDatosApi(datos);
            await cargarPromptCompleto();
        } catch (err) {
            error.value = err instanceof Error ? err.message : 'Error desconocido';
        } finally {
            loading.value = false;
        }
    };

    const guardarConfiguracion = async () => {
        saving.value = true;
        error.value = null;
        success.value = null;

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            const datosGuardar = {
                company_name: String(form.company_name || ''),
                vendedor_nombre: String(form.vendedor_nombre || ''),
                vendedor_genero: String(form.vendedor_genero || ''),
                celular: String(form.celular || ''),
                email: String(form.email || ''),
                website: form.website ? String(form.website) : null,
                descripcion_empresa: String(form.descripcion_empresa || ''),
                logo_path: form.logo_path ? String(form.logo_path) : null,
                actividad_economica: String(form.actividad_economica || ''),
                tono_bot: String(form.tono_bot || ''),
                estilo_comunicacion: String(form.estilo_comunicacion || ''),
                personalidad_bot: form.personalidad_bot ? String(form.personalidad_bot) : null,
                estilo_ventas: form.estilo_ventas ? String(form.estilo_ventas) : null,
                respuesta_si_es_bot: form.respuesta_si_es_bot ? String(form.respuesta_si_es_bot) : null,
                reglas_venta_criticas: form.reglas_venta_criticas ? String(form.reglas_venta_criticas) : null,
                moneda: String(form.moneda || 'PEN'),
                metodos_pago: Array.isArray(form.metodos_pago) ? form.metodos_pago : [],
                horario_atencion: form.horario_atencion ? String(form.horario_atencion) : null,
                politica_devoluciones: form.politica_devoluciones ? String(form.politica_devoluciones) : null,
                restricciones_especiales: form.restricciones_especiales ? String(form.restricciones_especiales) : null,
                address: form.address ? String(form.address) : null,
                social_networks: {
                    instagram: form.social_networks?.instagram ? String(form.social_networks.instagram) : '',
                    facebook: form.social_networks?.facebook ? String(form.social_networks.facebook) : '',
                    tiktok: form.social_networks?.tiktok ? String(form.social_networks.tiktok) : '',
                    website: form.social_networks?.website ? String(form.social_networks.website) : '',
                },
                standard_size: String(form.standard_size || 'ESTÁNDAR'),
                agente_ia_activado: form.configuracion_agente?.agente_ia_activado ?? false,
                agente_ia_modelo: form.configuracion_agente?.agente_ia_modelo ? String(form.configuracion_agente.agente_ia_modelo) : null,
                agente_ia_api_key: form.configuracion_agente?.agente_ia_api_key ? String(form.configuracion_agente.agente_ia_api_key) : null,
                agente_ia_temperatura: Number(form.configuracion_agente?.agente_ia_temperatura ?? 0.7),
                saludo_inicial: form.saludo_inicial ? String(form.saludo_inicial) : null,
                reglas_comunicacion: form.reglas_comunicacion ? String(form.reglas_comunicacion) : null,
                flujo_ventas: form.flujo_ventas ? String(form.flujo_ventas) : null,
                plantillas_datos: normalizarPlantillasDatos(form.plantillas_datos),
                horario_entregas: form.horario_entregas ? String(form.horario_entregas) : null,
                horario_shalom: form.horario_shalom ? String(form.horario_shalom) : null,
                protocolo_traspaso: form.protocolo_traspaso ? String(form.protocolo_traspaso) : null,
                mensaje_recordatorio_3min: form.mensaje_recordatorio_3min ? String(form.mensaje_recordatorio_3min) : null,
                mensaje_recordatorio_15min: form.mensaje_recordatorio_15min ? String(form.mensaje_recordatorio_15min) : null,
                mensaje_recordatorio_datos: form.mensaje_recordatorio_datos ? String(form.mensaje_recordatorio_datos) : null,
                formato_registro_venta: form.formato_registro_venta ? String(form.formato_registro_venta) : null,
                mensaje_comprobante_recibido: form.mensaje_comprobante_recibido ? String(form.mensaje_comprobante_recibido) : null,
                mensaje_comprobante_fuera_horario: form.mensaje_comprobante_fuera_horario ? String(form.mensaje_comprobante_fuera_horario) : null,
                mensaje_pedido_confirmado: form.mensaje_pedido_confirmado ? String(form.mensaje_pedido_confirmado) : null,
                mensaje_pedido_enviado: form.mensaje_pedido_enviado ? String(form.mensaje_pedido_enviado) : null,
                mensaje_pedido_entregado: form.mensaje_pedido_entregado ? String(form.mensaje_pedido_entregado) : null,
                mensaje_espera_link_tarjeta: form.mensaje_espera_link_tarjeta ? String(form.mensaje_espera_link_tarjeta) : null,
                comision_tarjeta: form.comision_tarjeta != null ? Number(form.comision_tarjeta) : null,
                link_pago_tarjeta: form.link_pago_tarjeta ? String(form.link_pago_tarjeta) : null,
            };

            const response = await fetch('/api/company-settings', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    Accept: 'application/json',
                },
                credentials: 'include',
                body: JSON.stringify(datosGuardar),
            });

            const contentType = response.headers.get('content-type');
            if (!contentType?.includes('application/json')) {
                throw new Error('Error del servidor: respuesta no válida');
            }

            const datos = await response.json();

            if (!response.ok) {
                throw new Error(datos.message || 'Error al guardar');
            }

            if (datos.configuracion_agente) {
                form.configuracion_agente = mapConfiguracionAgenteDesdeApi(
                    datos.configuracion_agente,
                    form.configuracion_agente,
                );
            }

            form.plantillas_datos = normalizarPlantillasDatos(datos.flujo?.plantillas_datos ?? form.plantillas_datos);

            if (datos.empresa?.standard_size || datos.standard_size) {
                form.standard_size = datos.empresa?.standard_size || datos.standard_size;
                invalidateStandardSizeCache(form.standard_size);
            }

            form.estadisticas = datos.estadisticas || form.estadisticas;

            await cargarPromptCompleto();

            success.value = 'Configuración guardada correctamente';

            setTimeout(() => {
                success.value = null;
            }, 3000);
        } catch (err) {
            error.value = err instanceof Error ? err.message : 'Error desconocido';
        } finally {
            saving.value = false;
        }
    };

    const agregarMetodoPago = (metodo: {
        nombre: string;
        descripcion: string;
        instrucciones?: string;
        qr_path?: string;
        tipo?: string;
        icono?: string;
        destinatario?: string;
        numero_cuenta?: string;
        imagen_url?: string;
    }) => {
        if (!Array.isArray(form.metodos_pago)) {
            form.metodos_pago = [];
        }
        form.metodos_pago.push(metodo);
    };

    const eliminarMetodoPago = (index: number) => {
        if (Array.isArray(form.metodos_pago)) {
            form.metodos_pago.splice(index, 1);
        }
    };

    const probarConexionIA = async (): Promise<string> => {
        const response = await fetch('/api/estado-ia', {
            credentials: 'include',
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            throw new Error('No se pudo verificar la IA');
        }

        const datos = await response.json();
        const prueba = datos.prueba_api;

        if (prueba?.exitosa) {
            return `IA conectada (${prueba.tiempo_ms}ms): ${prueba.respuesta}`;
        }

        throw new Error(prueba?.error || 'La IA no respondió correctamente');
    };

    const resetearConfiguracion = async () => {
        resetting.value = true;
        error.value = null;
        success.value = null;

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            const response = await fetch('/api/company-settings', {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    Accept: 'application/json',
                },
                credentials: 'include',
            });

            const contentType = response.headers.get('content-type');
            if (!contentType?.includes('application/json')) {
                throw new Error('Error del servidor: respuesta no válida');
            }

            const datos = await response.json();

            if (!response.ok) {
                throw new Error(datos.message || 'Error al restablecer');
            }

            aplicarDatosApi(datos);
            await cargarPromptCompleto();
            success.value = 'Configuración restablecida. Los productos del catálogo no se modificaron.';

            setTimeout(() => {
                success.value = null;
            }, 4000);
        } catch (err) {
            error.value = err instanceof Error ? err.message : 'Error desconocido';
        } finally {
            resetting.value = false;
        }
    };

    return {
        form,
        loading,
        saving,
        resetting,
        error,
        success,
        promptCompleto,
        promptSecciones,
        cargarDatos,
        cargarPromptCompleto,
        guardarConfiguracion,
        resetearConfiguracion,
        agregarMetodoPago,
        eliminarMetodoPago,
        probarConexionIA,
    };
}
