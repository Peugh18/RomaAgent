import type { ConfiguracionAgenteForm } from '@/types/settings';

/** Respuesta de GET /api/company-settings → configuracion_agente */
export interface ConfiguracionAgenteApi {
    activado?: boolean;
    modelo?: string;
    temperatura?: number;
    api_key_configurada?: boolean;
    modelos_disponibles?: Record<string, string>;
}

export function mapConfiguracionAgenteDesdeApi(
    api: ConfiguracionAgenteApi | null | undefined,
    actual?: ConfiguracionAgenteForm,
): ConfiguracionAgenteForm {
    return {
        agente_ia_activado: api?.activado ?? actual?.agente_ia_activado ?? false,
        agente_ia_modelo: api?.modelo ?? actual?.agente_ia_modelo ?? 'gemini-2.5-flash-lite',
        agente_ia_temperatura: api?.temperatura ?? actual?.agente_ia_temperatura ?? 0.7,
        agente_ia_api_key: '',
        api_key_configurada: api?.api_key_configurada ?? actual?.api_key_configurada ?? false,
        modelos_disponibles: api?.modelos_disponibles ?? actual?.modelos_disponibles ?? {},
    };
}
