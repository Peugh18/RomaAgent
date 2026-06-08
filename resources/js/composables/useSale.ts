import { ref, watch, type Ref } from 'vue';
import { apiJson, getCsrfToken } from '@/composables/useApi';
import type { Sale, SaleTransition } from '@/types/sale';
import { PIPELINE_ENTREGADOS_KANBAN_LIMIT, SALE_TRANSITION_ENDPOINTS } from '@/types/sale';

export function useActiveSale(phone: Ref<string | null>) {
    const sale = ref<Sale | null>(null);
    const loading = ref(false);
    const transitioning = ref(false);
    const error = ref<string | null>(null);

    const loadActiveSale = async () => {
        if (!phone.value) {
            sale.value = null;
            return;
        }

        loading.value = true;
        error.value = null;

        try {
            const encoded = encodeURIComponent(phone.value);
            sale.value = await apiJson<Sale | null>(`/api/sales/active/${encoded}`);
        } catch (err) {
            error.value = err instanceof Error ? err.message : 'Error al cargar pedido';
            sale.value = null;
        } finally {
            loading.value = false;
        }
    };

    const executeTransition = async (
        transition: SaleTransition,
        message: string,
        saleId?: number,
    ): Promise<Sale | null> => {
        const id = saleId ?? sale.value?.id;
        if (!id) {
            return null;
        }

        transitioning.value = true;
        error.value = null;

        try {
            const endpoint = SALE_TRANSITION_ENDPOINTS[transition];
            const updated = await apiJson<Sale>(`/api/sales/${id}/${endpoint}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                body: JSON.stringify({ message }),
            });

            if (sale.value?.id === updated.id) {
                sale.value = updated;
            }

            return updated;
        } catch (err) {
            error.value = err instanceof Error ? err.message : 'No se pudo completar la acción';
            return null;
        } finally {
            transitioning.value = false;
        }
    };

    watch(phone, () => {
        void loadActiveSale();
    });

    return {
        sale,
        loading,
        transitioning,
        error,
        loadActiveSale,
        executeTransition,
    };
}

export function usePipelineSales() {
    const sales = ref<Sale[]>([]);
    const loading = ref(false);
    const error = ref<string | null>(null);
    const entregadosTotal = ref(0);
    const entregadosArchivedCount = ref(0);
    const entregadosKanbanLimit = ref(15);

    const loadSales = async () => {
        loading.value = true;
        error.value = null;

        try {
            const response = await apiJson<{
                sales: Sale[];
                entregados_total: number;
                entregados_archived_count: number;
                entregados_kanban_limit: number;
            }>('/api/sales?pipeline=1');

            sales.value = response.sales ?? [];
            entregadosTotal.value = response.entregados_total ?? 0;
            entregadosArchivedCount.value = response.entregados_archived_count ?? 0;
            entregadosKanbanLimit.value = response.entregados_kanban_limit ?? 15;
        } catch (err) {
            error.value = err instanceof Error ? err.message : 'Error al cargar pedidos';
            sales.value = [];
            entregadosTotal.value = 0;
            entregadosArchivedCount.value = 0;
        } finally {
            loading.value = false;
        }
    };

    return {
        sales,
        loading,
        error,
        entregadosTotal,
        entregadosArchivedCount,
        entregadosKanbanLimit,
        loadSales,
    };
}

interface PaginatedSales {
    data: Sale[];
    current_page: number;
    last_page: number;
    total: number;
}

const pipelineHistorySales = ref<Sale[]>([]);
const pipelineHistoryLoading = ref(false);
const pipelineHistoryError = ref<string | null>(null);
const pipelineHistoryPage = ref(1);
const pipelineHistoryLastPage = ref(1);
const pipelineHistoryTotal = ref(0);
const pipelineHistorySearch = ref('');

export function usePipelineArchive() {
    const loadArchive = async () => {
        pipelineHistoryLoading.value = true;
        pipelineHistoryError.value = null;

        try {
            const params = new URLSearchParams({
                pipeline: '1',
                scope: 'archive',
                skip_recent: String(PIPELINE_ENTREGADOS_KANBAN_LIMIT),
                page: String(pipelineHistoryPage.value),
                per_page: '20',
            });

            if (pipelineHistorySearch.value.trim() !== '') {
                params.set('search', pipelineHistorySearch.value.trim());
            }

            const response = await apiJson<PaginatedSales>(`/api/sales?${params.toString()}`);
            pipelineHistorySales.value = response.data ?? [];
            pipelineHistoryPage.value = response.current_page ?? 1;
            pipelineHistoryLastPage.value = response.last_page ?? 1;
            pipelineHistoryTotal.value = response.total ?? pipelineHistorySales.value.length;
        } catch (err) {
            pipelineHistoryError.value = err instanceof Error ? err.message : 'Error al cargar archivo';
            pipelineHistorySales.value = [];
        } finally {
            pipelineHistoryLoading.value = false;
        }
    };

    return {
        sales: pipelineHistorySales,
        loading: pipelineHistoryLoading,
        error: pipelineHistoryError,
        page: pipelineHistoryPage,
        lastPage: pipelineHistoryLastPage,
        total: pipelineHistoryTotal,
        search: pipelineHistorySearch,
        loadArchive,
    };
}
