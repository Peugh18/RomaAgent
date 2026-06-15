export type SaleStatus =
    | 'consultando'
    | 'cotizando'
    | 'datos_listos'
    | 'pago_pendiente'
    | 'pago_recibido'
    | 'confirmado'
    | 'enviado'
    | 'entregado'
    | 'cancelado';

export type SaleTransition =
    | 'confirm_payment'
    | 'mark_shipped'
    | 'mark_delivered';

export interface SaleCustomerData {
    nombre?: string;
    name?: string;
    direccion?: string;
    address?: string;
    ubicacion_actual?: string;
    maps_url?: string;
    latitude?: number;
    longitude?: number;
    [key: string]: unknown;
}

export interface SaleItem {
    id: number;
    product_name: string;
    color: string | null;
    size: string;
    quantity: number;
    unit_price: number | string;
    subtotal: number | string;
}

export interface Sale {
    id: number;
    phone_number: string;
    product_name: string;
    color: string | null;
    size: string;
    quantity: number;
    unit_price: number | string;
    delivery_cost: number | string;
    total_amount: number | string;
    payment_method: string | null;
    delivery_type: string | null;
    delivery_district: string | null;
    status: SaleStatus;
    customer_data?: SaleCustomerData | null;
    payment_received_at: string | null;
    confirmed_at: string | null;
    shipped_at: string | null;
    delivered_at?: string | null;
    customer?: {
        id: number;
        name: string | null;
        phone_number: string;
        ia_paused: boolean;
        ia_pause_reason: string | null;
    };
    customer_name?: string | null;
    delivery_address?: string | null;
    maps_url?: string | null;
    comprobante_url?: string | null;
    notes?: string | null;
    can_confirm_payment?: boolean;
    can_mark_shipped?: boolean;
    can_mark_delivered?: boolean;
    can_cancel?: boolean;
    items?: SaleItem[];
}

export const SALE_STATUS_LABELS: Record<SaleStatus, string> = {
    consultando: 'Consultando',
    cotizando: 'Cotizando',
    datos_listos: 'Datos listos',
    pago_pendiente: 'Pago pendiente',
    pago_recibido: 'Pago recibido',
    confirmado: 'Confirmado',
    enviado: 'Enviado',
    entregado: 'Entregado',
    cancelado: 'Cancelado',
};

export const PIPELINE_COLUMNS: SaleStatus[] = [
    'consultando',
    'cotizando',
    'datos_listos',
    'pago_pendiente',
    'pago_recibido',
    'confirmado',
    'enviado',
    'entregado',
    'cancelado',
];

/** Columnas que siempre se muestran aunque estén vacías (flujo operativo). */
export const PIPELINE_ALWAYS_VISIBLE_COLUMNS: SaleStatus[] = [
    'pago_pendiente',
    'pago_recibido',
    'confirmado',
    'enviado',
    'entregado',
];

/** Columnas del tab "En progreso" (antes de logística). */
export const PIPELINE_PROGRESS_COLUMNS: SaleStatus[] = [
    'consultando',
    'cotizando',
    'datos_listos',
    'pago_pendiente',
    'pago_recibido',
];

/** Columnas del tab "Logística" (confirmado, enviado, entregado, cancelado). */
export const PIPELINE_LOGISTICS_COLUMNS: SaleStatus[] = [
    'confirmado',
    'enviado',
    'entregado',
    'cancelado',
];

export type PipelineTab = 'progress' | 'logistics';

/** Máximo de entregados recientes en la columna del kanban; el resto va al archivo. */
export const PIPELINE_ENTREGADOS_KANBAN_LIMIT = 15;

export function saleEsPagoTarjeta(sale: Sale): boolean {
    return (sale.payment_method ?? '').toLowerCase().includes('tarjeta');
}

export function saleCanConfirmPayment(sale: Sale): boolean {
    if (sale.can_confirm_payment !== undefined) {
        return sale.can_confirm_payment;
    }

    if (saleEsPagoTarjeta(sale)) {
        return sale.status === 'pago_pendiente' || sale.status === 'pago_recibido';
    }

    return sale.status === 'pago_recibido';
}

export function saleCanMarkShipped(sale: Sale): boolean {
    return sale.status === 'confirmado';
}

export function saleCanMarkDelivered(sale: Sale): boolean {
    return sale.status === 'enviado';
}

export function saleVerifyPaymentLabel(sale: Sale): string {
    if (saleEsPagoTarjeta(sale)) {
        return 'Confirmar pago tarjeta';
    }

    if (sale.status === 'pago_pendiente') {
        return 'Confirmar pago';
    }

    return 'Verificar comprobante';
}

export function salePaymentHint(sale: Sale): string {
    if (saleEsPagoTarjeta(sale)) {
        return 'Modo Humano: envía el link de pago en el chat. Cuando la clienta pague, confirma aquí.';
    }

    if (sale.status === 'datos_listos') {
        return 'Esperando que la clienta pague y envíe el comprobante por WhatsApp. No confirmes hasta ver el voucher en el chat.';
    }

    return 'Revisa el comprobante en el chat y pulsa verificar para confirmar el pedido y bajar stock.';
}

export function saleMarkShippedLabel(): string {
    return 'Marcar enviado';
}

export function saleMarkDeliveredLabel(): string {
    return 'Marcar entregado';
}

export const SALE_TRANSITION_ENDPOINTS: Record<SaleTransition, string> = {
    confirm_payment: 'confirm-payment',
    mark_shipped: 'mark-shipped',
    mark_delivered: 'mark-delivered',
};
