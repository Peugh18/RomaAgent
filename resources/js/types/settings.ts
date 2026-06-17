export interface SocialNetworksForm {
    instagram: string;
    facebook: string;
    tiktok: string;
    website?: string;
}

export interface ConfiguracionAgenteForm {
    agente_ia_activado: boolean;
    agente_ia_modelo: string;
    agente_ia_api_key: string;
    agente_ia_temperatura: number;
    api_key_configurada?: boolean;
    modelos_disponibles?: Record<string, string>;
}

export interface MetodoPago {
    nombre: string;
    descripcion: string;
    instrucciones?: string;
    qr_path?: string;
    tipo?: string;
    icono?: string;
    destinatario?: string;
    numero_cuenta?: string;
    imagen_url?: string;
    [key: string]: unknown;
}

export interface CompanySettingsForm {
    id?: number;
    company_name: string;
    ruc?: string;
    razon_social?: string;
    vendedor_nombre?: string;
    vendedor_genero?: string;
    celular?: string;
    email?: string;
    website?: string;
    descripcion_empresa?: string;
    logo_path?: string;
    actividad_economica?: string;
    tono_bot?: string;
    estilo_comunicacion?: string;
    personalidad_bot?: string;
    estilo_ventas?: string;
    respuesta_si_es_bot?: string;
    reglas_venta_criticas?: string;
    moneda?: string;
    metodos_pago?: MetodoPago[];
    horario_atencion?: string;
    politica_devoluciones?: string;
    restricciones_especiales?: string;
    social_networks: SocialNetworksForm;
    address: string;
    standard_size: string;
    saludo_inicial?: string;
    reglas_comunicacion?: string;
    plantillas_datos?: { motorizado: Record<string, string>; shalom: Record<string, string> };
    horario_entregas?: string;
    horario_shalom?: string;
    protocolo_traspaso?: string;
    mensaje_recordatorio_3min?: string;
    mensaje_recordatorio_15min?: string;
    mensaje_recordatorio_datos?: string;
    comision_tarjeta?: number;
    configuracion_agente?: ConfiguracionAgenteForm;
    [key: string]: unknown;
}

/** Clave interna de talla estándar en BD (UNICA = talla estándar al cliente; configurable por empresa). */
export const DEFAULT_STANDARD_SIZE = 'ESTÁNDAR';

export interface VariantStockShape {
    sizes_stock: Record<string, number>;
    tempSize?: string;
    tempStock?: number | null;
}

export function normalizeSizeKey(value: string): string {
    return value.trim().toUpperCase();
}

export function getStandardStock(variant: VariantStockShape, standardSizeKey: string): number {
    return variant.sizes_stock[normalizeSizeKey(standardSizeKey)] ?? 0;
}

export function setStandardStock(variant: VariantStockShape, standardSizeKey: string, stock: number): void {
    const key = normalizeSizeKey(standardSizeKey);
    if (stock < 0) {
        return;
    }
    variant.sizes_stock[key] = stock;
}

export function extraSizesForVariant(
    variant: VariantStockShape,
    standardSizeKey: string,
): Record<string, number> {
    const standard = normalizeSizeKey(standardSizeKey);

    return Object.fromEntries(
        Object.entries(variant.sizes_stock).filter(([size]) => normalizeSizeKey(size) !== standard),
    );
}

export function addExtraSizeToVariant(
    variant: VariantStockShape,
    standardSizeKey: string,
): boolean {
    const size = normalizeSizeKey(variant.tempSize ?? '');
    const stock = variant.tempStock;

    if (!size || size === normalizeSizeKey(standardSizeKey)) {
        return false;
    }
    if (stock === undefined || stock === null || stock < 0) {
        return false;
    }

    variant.sizes_stock[size] = stock;
    variant.tempSize = '';
    variant.tempStock = null;

    return true;
}

export function removeExtraSizeFromVariant(variant: VariantStockShape, size: string, standardSizeKey: string): void {
    if (normalizeSizeKey(size) === normalizeSizeKey(standardSizeKey)) {
        return;
    }
    delete variant.sizes_stock[size];
}
