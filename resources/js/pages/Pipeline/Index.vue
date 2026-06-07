<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/crm/PageHeader.vue';
import PipelineColumn from '@/components/pipeline/PipelineColumn.vue';
import PipelineEmptyState from '@/components/pipeline/PipelineEmptyState.vue';
import PipelineEntregadosArchivo from '@/components/pipeline/PipelineEntregadosArchivo.vue';
import PipelineColumnNav from '@/components/pipeline/PipelineColumnNav.vue';
import PipelineToolbar from '@/components/pipeline/PipelineToolbar.vue';
import SaleTransitionModal from '@/components/sales/SaleTransitionModal.vue';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { apiJson, getCsrfToken } from '@/composables/useApi';
import { type BreadcrumbItem } from '@/types';
import {
    PIPELINE_REVERT_ENDPOINTS,
    resolvePipelineMove,
    resolveSaleFromDragAdd,
    type PipelineRevertEndpoint,
} from '@/lib/pipelineTransitions';
import { usePipelineSales } from '@/composables/useSale';
import { PIPELINE_ALWAYS_VISIBLE_COLUMNS, PIPELINE_COLUMNS, type Sale, type SaleStatus, type SaleTransition } from '@/types/sale';
import { useCurrency } from '@/composables/useCurrency';
import { Head } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { RefreshCw } from 'lucide-vue-next';
import type { SortableEvent } from 'sortablejs';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Pipeline de ventas', href: '/pipeline' }];

const { sales, loading, error, loadSales, entregadosTotal, entregadosArchivedCount } = usePipelineSales();

const searchQuery = ref('');
const kanbanRef = ref<HTMLElement | null>(null);
const archivoOpen = ref(false);

const columnSales = ref<Record<SaleStatus, Sale[]>>(
    Object.fromEntries(PIPELINE_COLUMNS.map((status) => [status, [] as Sale[]])) as Record<SaleStatus, Sale[]>,
);

const transitionOpen = ref(false);
const transitionSale = ref<Sale | null>(null);
const activeTransition = ref<SaleTransition | null>(null);
const dragError = ref<string | null>(null);

const filteredSales = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();
    if (query === '') {
        return sales.value;
    }

    return sales.value.filter((sale) => {
        const name = (sale.customer_name ?? sale.customer?.name ?? '').toLowerCase();

        return (
            sale.phone_number.includes(query)
            || sale.product_name.toLowerCase().includes(query)
            || (sale.color?.toLowerCase().includes(query) ?? false)
            || name.includes(query)
            || String(sale.id).includes(query)
        );
    });
});

const syncColumnsFromSales = () => {
    for (const status of PIPELINE_COLUMNS) {
        columnSales.value[status] = filteredSales.value.filter((sale) => sale.status === status);
    }
};

watch([sales, filteredSales], () => {
    if (!transitionOpen.value) {
        syncColumnsFromSales();
    }
}, { immediate: true });

onMounted(() => {
    void loadSales();
});

const { format: formatMoney } = useCurrency();

const pipelineTotal = computed(() =>
    filteredSales.value.reduce((sum, s) => sum + Number(s.total_amount), 0),
);

const needsPaymentAttention = computed(() =>
    sales.value.filter((sale) => sale.status === 'pago_pendiente' || sale.status === 'pago_recibido').length,
);

const visibleColumns = computed(() => {
    const hasSearch = searchQuery.value.trim() !== '';

    if (hasSearch) {
        return PIPELINE_COLUMNS.filter((status) => columnSales.value[status].length > 0);
    }

    return PIPELINE_COLUMNS.filter(
        (status) =>
            PIPELINE_ALWAYS_VISIBLE_COLUMNS.includes(status)
            || columnSales.value[status].length > 0,
    );
});

const columnCounts = computed(() =>
    Object.fromEntries(
        PIPELINE_COLUMNS.map((status) => [status, columnSales.value[status]?.length ?? 0]),
    ) as Record<SaleStatus, number>,
);

const scrollToStatus = (status: SaleStatus) => {
    const column = kanbanRef.value?.querySelector(`[data-pipeline-status="${status}"]`);
    column?.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
};

const focusPayments = () => {
    if (sales.value.some((sale) => sale.status === 'pago_recibido')) {
        scrollToStatus('pago_recibido');
        return;
    }

    scrollToStatus('pago_pendiente');
};

const openTransition = (sale: Sale, transition: SaleTransition) => {
    transitionSale.value = sale;
    activeTransition.value = transition;
    transitionOpen.value = true;
    dragError.value = null;
};

const revertSale = async (sale: Sale, endpoint: PipelineRevertEndpoint) => {
    await apiJson(`/api/sales/${sale.id}/${PIPELINE_REVERT_ENDPOINTS[endpoint]}`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': getCsrfToken(),
        },
    });
};

const handleColumnChange = async ({
    toStatus,
    event,
}: {
    toStatus: SaleStatus;
    event: SortableEvent;
}) => {
    await nextTick();

    const sale = resolveSaleFromDragAdd(sales.value, columnSales.value, toStatus, event);
    if (!sale) {
        dragError.value = 'No se pudo identificar el pedido arrastrado.';
        await loadSales();
        return;
    }

    const fromStatus = sale.status;
    const action = resolvePipelineMove(fromStatus, toStatus);

    if (action.type === 'invalid') {
        dragError.value = 'Ese movimiento no está permitido. Usa los botones en columnas de pago.';
        await loadSales();
        return;
    }

    if (action.type === 'modal') {
        openTransition(sale, action.transition);
        return;
    }

    if (!window.confirm(action.message)) {
        await loadSales();
        return;
    }

    try {
        await revertSale(sale, action.endpoint);
        await loadSales();
    } catch (err) {
        dragError.value = err instanceof Error ? err.message : 'No se pudo revertir el pedido';
        await loadSales();
    }
};

const onTransitionCompleted = async () => {
    transitionOpen.value = false;
    await loadSales();
};

const onTransitionCancelled = async () => {
    transitionOpen.value = false;
    await loadSales();
};
</script>

<template>
    <Head title="Pipeline de ventas" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="crm-page space-y-6">
            <PageHeader
                title="Pipeline de ventas"
                description="Confirma pagos con botones. En Confirmado → Enviado → Entregado puedes arrastrar o usar botones; siempre se abre el modal de WhatsApp al avanzar."
            >
                <template #actions>
                    <Button variant="outline" class="gap-2" :disabled="loading" @click="loadSales">
                        <RefreshCw class="h-4 w-4" :class="loading ? 'animate-spin' : ''" />
                        Actualizar
                    </Button>
                </template>
            </PageHeader>

            <PipelineToolbar
                v-if="!loading || sales.length > 0"
                v-model:search="searchQuery"
                :total-orders="filteredSales.length"
                :total-amount="pipelineTotal"
                :needs-payment-attention="needsPaymentAttention"
                :loading="loading"
                @focus-payments="focusPayments"
                @clear-search="searchQuery = ''"
            />

            <div v-if="error || dragError" class="rounded-lg border border-destructive/50 bg-destructive/10 p-4 text-sm text-destructive">
                {{ error || dragError }}
            </div>

            <div v-if="loading && sales.length === 0" class="flex snap-x snap-mandatory gap-3 overflow-x-auto pb-4">
                <div v-for="i in 8" :key="i" class="min-w-[280px] flex-1 shrink-0 snap-center">
                    <div class="rounded-xl border bg-card p-4 shadow-sm">
                        <Skeleton class="mb-3 h-5 w-24" />
                        <Skeleton class="h-20 w-full" />
                    </div>
                </div>
            </div>

            <PipelineEmptyState v-else-if="sales.length === 0" />

            <template v-else-if="filteredSales.length === 0">
                <div class="rounded-xl border border-dashed py-12 text-center">
                    <p class="text-sm font-medium">Sin resultados para «{{ searchQuery }}»</p>
                    <Button variant="link" class="mt-2" @click="searchQuery = ''">Limpiar búsqueda</Button>
                </div>
            </template>

            <template v-else>
                <PipelineColumnNav
                    :columns="visibleColumns"
                    :counts="columnCounts"
                    @navigate="scrollToStatus"
                />

                <div
                    ref="kanbanRef"
                    class="flex snap-x snap-mandatory gap-3 overflow-x-auto pb-4 scroll-smooth"
                >
                    <PipelineColumn
                        v-for="status in visibleColumns"
                        :key="status"
                        :status="status"
                        :sales="columnSales[status]"
                        :highlight-attention="status === 'pago_pendiente' || status === 'pago_recibido'"
                        :entregados-total="status === 'entregado' ? entregadosTotal : undefined"
                        :archived-count="status === 'entregado' ? entregadosArchivedCount : undefined"
                        @update:sales="columnSales[status] = $event"
                        @open-transition="openTransition"
                        @column-change="handleColumnChange"
                        @open-archive="archivoOpen = true"
                    />
                </div>
            </template>

            <PipelineEntregadosArchivo
                v-model:open="archivoOpen"
                :archived-count="entregadosArchivedCount"
                @reverted="loadSales"
            />

            <SaleTransitionModal
                v-model:open="transitionOpen"
                :sale="transitionSale"
                :transition="activeTransition"
                @completed="onTransitionCompleted"
                @cancelled="onTransitionCancelled"
            />
        </div>
    </AppLayout>
</template>
