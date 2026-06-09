import type { Sale, SaleStatus, SaleTransition } from '@/types/sale';
import type { SortableEvent } from 'sortablejs';

export type PipelineRevertEndpoint = 'revert-delivered' | 'revert-shipped';

export type PipelineMoveAction =
    | { type: 'modal'; transition: SaleTransition }
    | { type: 'revert'; endpoint: PipelineRevertEndpoint; message: string }
    | { type: 'invalid' };

const LOGISTICS_COLUMNS: SaleStatus[] = ['confirmado', 'enviado', 'entregado', 'cancelado'];

export function isDraggablePipelineColumn(status: SaleStatus): boolean {
    return ['confirmado', 'enviado', 'entregado'].includes(status);
}

export function resolvePipelineMove(from: SaleStatus, to: SaleStatus): PipelineMoveAction {
    if (from === to) {
        return { type: 'invalid' };
    }

    if (from === 'confirmado' && to === 'enviado') {
        return { type: 'modal', transition: 'mark_shipped' };
    }

    if (from === 'enviado' && to === 'entregado') {
        return { type: 'modal', transition: 'mark_delivered' };
    }

    if (from === 'entregado' && to === 'enviado') {
        return {
            type: 'revert',
            endpoint: 'revert-delivered',
            message: '¿Reabrir este pedido? Volverá a Enviado y el bot se pausará de nuevo.',
        };
    }

    if (from === 'enviado' && to === 'confirmado') {
        return {
            type: 'revert',
            endpoint: 'revert-shipped',
            message: '¿Revertir el envío? El pedido volverá a Confirmado sin enviar mensaje.',
        };
    }

    return { type: 'invalid' };
}

export const PIPELINE_REVERT_ENDPOINTS: Record<PipelineRevertEndpoint, string> = {
    'revert-delivered': 'revert-delivered',
    'revert-shipped': 'revert-shipped',
};

export function resolveSaleFromDragAdd(
    allSales: Sale[],
    columnSales: Record<SaleStatus, Sale[]>,
    toStatus: SaleStatus,
    event: SortableEvent,
): Sale | null {
    const newIndex = event.newIndex ?? event.newDraggableIndex;

    if (newIndex !== undefined && newIndex !== null) {
        const byIndex = columnSales[toStatus]?.[newIndex];
        if (byIndex && typeof byIndex.id === 'number') {
            return byIndex;
        }
    }

    const element = event.item;
    const saleIdAttr =
        element?.dataset?.saleId
        ?? element?.querySelector?.('[data-sale-id]')?.getAttribute('data-sale-id');

    if (saleIdAttr) {
        const id = Number(saleIdAttr);
        if (!Number.isNaN(id)) {
            return allSales.find((sale) => sale.id === id) ?? null;
        }
    }

    return null;
}
