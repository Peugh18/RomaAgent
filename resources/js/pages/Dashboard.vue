<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import DashboardHero from '@/components/dashboard/DashboardHero.vue';
import PipelineOverviewChart from '@/components/dashboard/PipelineOverviewChart.vue';
import RecentOrdersList from '@/components/dashboard/RecentOrdersList.vue';
import SalesChart from '@/components/dashboard/SalesChart.vue';
import StatCard from '@/components/dashboard/StatCard.vue';
import CrmAlert from '@/components/crm/CrmAlert.vue';
import { Button } from '@/components/ui/button';
import { type BreadcrumbItem } from '@/types';
import { type SaleStatus } from '@/types/sale';
import { useCurrency } from '@/composables/useCurrency';
import { Head, Link } from '@inertiajs/vue3';
import { AlertCircle, MessageSquare, Package, TrendingUp, CreditCard, ArrowRight } from 'lucide-vue-next';
import { computed } from 'vue';

interface ChartDataPoint {
    date: string;
    label: string;
    sales: number;
    orders: number;
}

interface RecentOrder {
    id: number;
    product_name: string;
    color: string | null;
    phone_number: string;
    customer_name: string | null;
    total_amount: number;
    status: SaleStatus;
    status_label: string;
    created_at: string | null;
}

interface PipelineSlice {
    status: string;
    label: string;
    count: number;
}

const props = defineProps<{
    stats: {
        conversaciones_hoy: number;
        pendientes_pago: number;
        productos_activos: number;
        ventas_hoy: number;
        ventas_ayer: number;
        ventas_mes: number;
        pedidos_activos: number;
        ventas_trend: number | null;
    };
    pedidosRecientes: RecentOrder[];
    chartData: ChartDataPoint[];
    pipelineOverview: PipelineSlice[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];

const { format: formatMoney } = useCurrency();

const salesSparkline = computed(() => props.chartData.map((point) => point.sales));

const statCards = computed(() => [
    {
        title: 'Ventas hoy',
        value: formatMoney(props.stats.ventas_hoy),
        subtitle: props.stats.ventas_ayer > 0 ? `Ayer: ${formatMoney(props.stats.ventas_ayer)}` : 'Sin ventas ayer',
        icon: CreditCard,
        variant: 'success' as const,
        href: '/pipeline',
        trend: props.stats.ventas_trend,
        trendLabel: 'vs ayer',
        sparkline: salesSparkline.value,
    },
    {
        title: 'Conversaciones',
        value: props.stats.conversaciones_hoy,
        subtitle: 'Contactos únicos hoy',
        icon: MessageSquare,
        variant: 'default' as const,
        href: '/chat',
    },
    {
        title: 'Pagos pendientes',
        value: props.stats.pendientes_pago,
        subtitle: 'Esperan confirmación',
        icon: TrendingUp,
        variant: 'warning' as const,
        href: '/pipeline',
    },
    {
        title: 'Productos activos',
        value: props.stats.productos_activos,
        subtitle: 'Disponibles en catálogo',
        icon: Package,
        variant: 'default' as const,
        href: '/productos',
    },
]);
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="crm-page">
            <DashboardHero :ventas-mes="stats.ventas_mes" :pedidos-activos="stats.pedidos_activos" />

            <CrmAlert v-if="stats.pendientes_pago > 0" variant="warning" class="!border-amber-500/30 !bg-amber-500/10">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-start gap-3">
                        <AlertCircle class="mt-0.5 h-5 w-5 shrink-0" />
                        <div>
                            <p class="font-medium">
                                {{ stats.pendientes_pago }} pedido{{ stats.pendientes_pago === 1 ? '' : 's' }} esperan confirmación de pago
                            </p>
                            <p class="mt-0.5 text-sm opacity-90">
                                Revisa el comprobante en el chat y confirma desde el pipeline.
                            </p>
                        </div>
                    </div>
                    <Button as-child variant="secondary" class="shrink-0 gap-2">
                        <Link href="/pipeline">
                            Ir al pipeline
                            <ArrowRight class="h-4 w-4" />
                        </Link>
                    </Button>
                </div>
            </CrmAlert>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard
                    v-for="(card, index) in statCards"
                    :key="card.title"
                    v-motion
                    :initial="{ opacity: 0, y: 16 }"
                    :enter="{ opacity: 1, y: 0, transition: { delay: 70 * index, duration: 320, ease: 'easeOut' } }"
                    :title="card.title"
                    :value="card.value"
                    :subtitle="card.subtitle"
                    :icon="card.icon"
                    :variant="card.variant"
                    :href="card.href"
                    :trend="card.trend"
                    :trend-label="card.trendLabel"
                    :sparkline="card.sparkline"
                />
            </div>

            <div
                v-motion
                :initial="{ opacity: 0, y: 16 }"
                :enter="{ opacity: 1, y: 0, transition: { delay: 280, duration: 350, ease: 'easeOut' } }"
                class="grid gap-6 xl:grid-cols-5"
            >
                <SalesChart :data="chartData" class="xl:col-span-3" />
                <PipelineOverviewChart :data="pipelineOverview" class="xl:col-span-2" />
            </div>

            <RecentOrdersList
                v-motion
                :initial="{ opacity: 0, y: 16 }"
                :enter="{ opacity: 1, y: 0, transition: { delay: 360, duration: 350, ease: 'easeOut' } }"
                :orders="pedidosRecientes"
            />
        </div>
    </AppLayout>
</template>
