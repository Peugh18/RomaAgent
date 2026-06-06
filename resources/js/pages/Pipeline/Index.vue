<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import CrmPanel from '@/components/crm/CrmPanel.vue';
import PageHeader from '@/components/crm/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { type BreadcrumbItem } from '@/types';
import { PIPELINE_COLUMNS, SALE_STATUS_LABELS, type Sale, type SaleStatus } from '@/types/sale';
import { usePipelineSales } from '@/composables/useSale';
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted } from 'vue';
import { Kanban, Loader2, RefreshCw } from 'lucide-vue-next';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Pipeline de ventas', href: '/pipeline' }];

const { sales, loading, error, loadSales } = usePipelineSales();

onMounted(() => {
    void loadSales();
});

const salesByStatus = computed(() => {
    const grouped = Object.fromEntries(
        PIPELINE_COLUMNS.map((status) => [status, [] as Sale[]]),
    ) as Record<SaleStatus, Sale[]>;

    for (const sale of sales.value) {
        if (grouped[sale.status]) {
            grouped[sale.status].push(sale);
        }
    }

    return grouped;
});

const formatMoney = (value: number | string) => Number(value).toFixed(2);
</script>

<template>
    <Head title="Pipeline de ventas" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="crm-page space-y-6">
            <PageHeader
                title="Pipeline de ventas"
                description="Pedidos reales desde consulta hasta envío. La IA crea y avanza pedidos; tú confirmas pagos desde el chat."
            >
                <Button variant="outline" class="gap-2" :disabled="loading" @click="loadSales">
                    <RefreshCw class="h-4 w-4" :class="loading ? 'animate-spin' : ''" />
                    Actualizar
                </Button>
            </PageHeader>

            <p v-if="error" class="text-sm text-red-600">{{ error }}</p>

            <div v-if="loading && sales.length === 0" class="flex justify-center py-12">
                <Loader2 class="h-6 w-6 animate-spin text-muted-foreground" />
            </div>

            <div v-else class="flex gap-3 overflow-x-auto pb-4">
                <div
                    v-for="status in PIPELINE_COLUMNS"
                    :key="status"
                    class="min-w-[220px] flex-1 shrink-0"
                >
                    <CrmPanel class="h-full">
                        <div class="mb-3 flex items-center justify-between border-b border-border pb-2">
                            <h3 class="text-sm font-semibold">{{ SALE_STATUS_LABELS[status] }}</h3>
                            <span class="rounded-full bg-muted px-2 py-0.5 text-xs font-medium">
                                {{ salesByStatus[status].length }}
                            </span>
                        </div>

                        <div class="space-y-2">
                            <div
                                v-for="sale in salesByStatus[status]"
                                :key="sale.id"
                                class="rounded-lg border border-border bg-muted/20 p-3 text-sm"
                            >
                                <p class="font-medium">{{ sale.product_name }}</p>
                                <p v-if="sale.color" class="text-xs text-muted-foreground">{{ sale.color }}</p>
                                <p class="mt-1 text-xs">{{ sale.phone_number }}</p>
                                <p class="mt-2 font-semibold">S/ {{ formatMoney(sale.total_amount) }}</p>
                                <Button as-child size="sm" variant="link" class="mt-1 h-auto p-0 text-xs">
                                    <Link :href="`/chat?phone=${encodeURIComponent(sale.phone_number)}`">
                                        Ver chat
                                    </Link>
                                </Button>
                            </div>

                            <p
                                v-if="salesByStatus[status].length === 0"
                                class="py-4 text-center text-xs text-muted-foreground"
                            >
                                Sin pedidos
                            </p>
                        </div>
                    </CrmPanel>
                </div>
            </div>

            <p v-if="!loading && sales.length === 0" class="flex items-center gap-2 text-sm text-muted-foreground">
                <Kanban class="h-4 w-4" />
                Aún no hay pedidos. Cuando la IA registre ventas, aparecerán aquí.
            </p>
        </div>
    </AppLayout>
</template>
