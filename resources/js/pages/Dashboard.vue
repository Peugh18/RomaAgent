<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import CrmPanel from '@/components/crm/CrmPanel.vue';
import { type BreadcrumbItem } from '@/types';
import { SALE_STATUS_LABELS, type SaleStatus } from '@/types/sale';
import { Head, Link } from '@inertiajs/vue3';
import { useCurrency } from '@/composables/useCurrency';
import { LayoutGrid, MessageSquare, Package, TrendingUp, ArrowRight } from 'lucide-vue-next';

const props = defineProps<{
    stats: {
        conversaciones_hoy: number;
        pendientes_pago: number;
        productos_activos: number;
        ventas_hoy: number;
        ventas_mes: number;
    };
    pedidosRecientes: {
        id: number;
        product_name: string;
        color: string | null;
        phone_number: string;
        total_amount: number;
        status: SaleStatus;
        status_label: string;
        created_at: string | null;
    }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];

const { format: formatMoney } = useCurrency();

const cards = [
    { key: 'conversaciones_hoy', label: 'Conversaciones hoy', icon: MessageSquare, tone: 'text-emerald-600 bg-emerald-50 dark:bg-emerald-950/40', format: (v: number) => String(v) },
    { key: 'pendientes_pago', label: 'Pagos por confirmar', icon: TrendingUp, tone: 'text-amber-600 bg-amber-50 dark:bg-amber-950/40', format: (v: number) => String(v) },
    { key: 'productos_activos', label: 'Productos activos', icon: Package, tone: 'text-violet-600 bg-violet-50 dark:bg-violet-950/40', format: (v: number) => String(v) },
    { key: 'ventas_hoy', label: 'Ventas confirmadas hoy', icon: LayoutGrid, tone: 'text-sky-600 bg-sky-50 dark:bg-sky-950/40', format: (v: number) => formatMoney(v) },
] as const;
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Dashboard</h1>
                    <p class="text-sm text-muted-foreground">
                        Ventas reales · Mes: {{ formatMoney(stats.ventas_mes) }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <Link href="/pipeline" class="inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline">
                        Ver pipeline <ArrowRight class="h-4 w-4" />
                    </Link>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div
                    v-for="card in cards"
                    :key="card.key"
                    class="rounded-xl border border-border bg-card p-5 shadow-sm"
                >
                    <div class="flex items-center justify-between">
                        <p class="text-sm text-muted-foreground">{{ card.label }}</p>
                        <div class="rounded-lg p-2" :class="card.tone">
                            <component :is="card.icon" class="h-4 w-4" />
                        </div>
                    </div>
                    <p class="mt-3 text-2xl font-semibold">{{ card.format(stats[card.key]) }}</p>
                </div>
            </div>

            <CrmPanel>
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-semibold">Pedidos recientes</h2>
                    <Link href="/pipeline" class="text-sm text-primary hover:underline">Ver todos</Link>
                </div>
                <div v-if="pedidosRecientes.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                    Aún no hay pedidos. La IA los creará al vender por WhatsApp.
                </div>
                <div v-else class="divide-y divide-border">
                    <div
                        v-for="pedido in pedidosRecientes"
                        :key="pedido.id"
                        class="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0"
                    >
                        <div>
                            <p class="font-medium">{{ pedido.product_name }} <span v-if="pedido.color" class="text-muted-foreground">· {{ pedido.color }}</span></p>
                            <p class="text-xs text-muted-foreground">{{ pedido.phone_number }}</p>
                        </div>
                        <div class="text-right">
                            <p class="font-semibold">{{ formatMoney(pedido.total_amount) }}</p>
                            <p class="text-xs text-muted-foreground">{{ SALE_STATUS_LABELS[pedido.status] ?? pedido.status_label }}</p>
                        </div>
                    </div>
                </div>
            </CrmPanel>
        </div>
    </AppLayout>
</template>
