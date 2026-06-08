import type { SaleStatus } from '@/types/sale';

export const SALE_STATUS_CHART_COLORS: Record<SaleStatus, string> = {
    consultando: 'hsl(215 16% 47%)',
    cotizando: 'hsl(217 91% 60%)',
    datos_listos: 'hsl(263 70% 58%)',
    pago_pendiente: 'hsl(38 92% 50%)',
    pago_recibido: 'hsl(32 95% 44%)',
    confirmado: 'hsl(142 71% 45%)',
    enviado: 'hsl(199 89% 48%)',
    entregado: 'hsl(160 84% 39%)',
    cancelado: 'hsl(0 84% 60%)',
};

export function saleStatusChartColor(status: string): string {
    return SALE_STATUS_CHART_COLORS[status as SaleStatus] ?? 'hsl(215 16% 47%)';
}
