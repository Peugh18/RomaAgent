import { ref, watch, type Ref } from 'vue';
import { apiJson } from '@/composables/useApi';
import type { Sale } from '@/types/sale';

export function useActiveSale(phone: Ref<string | null>) {
    const sale = ref<Sale | null>(null);
    const loading = ref(false);
    const confirming = ref(false);
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

    const confirmPayment = async (): Promise<boolean> => {
        if (!sale.value) {
            return false;
        }

        confirming.value = true;
        error.value = null;

        try {
            sale.value = await apiJson<Sale>(`/api/sales/${sale.value.id}/confirm-payment`, {
                method: 'POST',
            });
            return true;
        } catch (err) {
            error.value = err instanceof Error ? err.message : 'No se pudo confirmar';
            return false;
        } finally {
            confirming.value = false;
        }
    };

    watch(phone, () => {
        void loadActiveSale();
    });

    return {
        sale,
        loading,
        confirming,
        error,
        loadActiveSale,
        confirmPayment,
    };
}

export function usePipelineSales() {
    const sales = ref<Sale[]>([]);
    const loading = ref(false);
    const error = ref<string | null>(null);

    const loadSales = async () => {
        loading.value = true;
        error.value = null;

        try {
            sales.value = await apiJson<Sale[]>('/api/sales?pipeline=1');
        } catch (err) {
            error.value = err instanceof Error ? err.message : 'Error al cargar pedidos';
        } finally {
            loading.value = false;
        }
    };

    return {
        sales,
        loading,
        error,
        loadSales,
    };
}
