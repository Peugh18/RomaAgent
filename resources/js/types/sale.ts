export type SaleStatus =
    | 'consultando'
    | 'cotizando'
    | 'datos_listos'
    | 'pago_pendiente'
    | 'pago_recibido'
    | 'confirmado'
    | 'enviado'
    | 'cancelado';

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
    payment_received_at: string | null;
    confirmed_at: string | null;
    shipped_at: string | null;
    customer?: {
        id: number;
        name: string | null;
        phone_number: string;
        ia_paused: boolean;
        ia_pause_reason: string | null;
    };
}

export const SALE_STATUS_LABELS: Record<SaleStatus, string> = {
    consultando: 'Consultando',
    cotizando: 'Cotizando',
    datos_listos: 'Datos listos',
    pago_pendiente: 'Pago pendiente',
    pago_recibido: 'Pago recibido',
    confirmado: 'Confirmado',
    enviado: 'Enviado',
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
];

export function saleCanConfirmPayment(status: SaleStatus): boolean {
    return status === 'pago_pendiente' || status === 'pago_recibido';
}
