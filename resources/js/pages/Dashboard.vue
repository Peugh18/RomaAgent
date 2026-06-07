<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import RecentOrdersList from '@/components/dashboard/RecentOrdersList.vue';
import SalesChart from '@/components/dashboard/SalesChart.vue';
import StatCard from '@/components/dashboard/StatCard.vue';
import PageHeader from '@/components/crm/PageHeader.vue';
import CrmAlert from '@/components/crm/CrmAlert.vue';
import { Button } from '@/components/ui/button';
import { type BreadcrumbItem } from '@/types';
import { type SaleStatus } from '@/types/sale';
import { useCurrency } from '@/composables/useCurrency';
import { Head, Link } from '@inertiajs/vue3';
import { AlertCircle, MessageSquare, Package, TrendingUp, CreditCard, ArrowRight } from 'lucide-vue-next';

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

defineProps<{
    stats: {
        conversaciones_hoy: number;
        pendientes_pago: number;
        productos_activos: number;
        ventas_hoy: number;
        ventas_mes: number;
    };
    pedidosRecientes: RecentOrder[];
    chartData: ChartDataPoint[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];

const { format: formatMoney } = useCurrency();
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="crm-page">
            <PageHeader
                title="Dashboard"
                :description="`Resumen ejecutivo · Ventas del mes: ${formatMoney(stats.ventas_mes)}`"
            />

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

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    title="Ventas Hoy"
                    :value="formatMoney(stats.ventas_hoy)"
                    :icon="CreditCard"
                    variant="success"
                    href="/pipeline"
                />
                <StatCard
                    title="Conversaciones"
                    :value="stats.conversaciones_hoy"
                    :icon="MessageSquare"
                    variant="default"
                    href="/chat"
                />
                <StatCard
                    title="Pagos Pendientes"
                    :value="stats.pendientes_pago"
                    :icon="TrendingUp"
                    variant="warning"
                    href="/pipeline"
                />
                <StatCard
                    title="Productos Activos"
                    :value="stats.productos_activos"
                    :icon="Package"
                    variant="default"
                    href="/productos"
                />
            </div>

            <div class="grid gap-6 lg:grid-cols-5">
                <SalesChart :data="chartData" class="lg:col-span-3" />
                <RecentOrdersList :orders="pedidosRecientes" class="lg:col-span-2" />
            </div>
        </div>
    </AppLayout>
</template>
