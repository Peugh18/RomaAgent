<script setup lang="ts">
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useChartTheme } from '@/composables/useChartTheme';
import { computed, nextTick, onMounted, ref } from 'vue';
import { Line } from 'vue-chartjs';
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Tooltip,
    Legend,
    Filler,
    type ChartOptions,
} from 'chart.js';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Tooltip, Legend, Filler);

interface ChartDataPoint {
    date: string;
    label: string;
    sales: number;
    orders: number;
}

const props = defineProps<{
    data: ChartDataPoint[];
    loading?: boolean;
}>();

const theme = useChartTheme();
const chartReady = ref(false);

onMounted(async () => {
    theme.refresh();
    await nextTick();
    chartReady.value = true;
});

const hslWithAlpha = (hsl: string, alpha: number): string => hsl.replace(/\)$/, ` / ${alpha})`);

const chartData = computed(() => ({
    labels: props.data.map((d) => d.label),
    datasets: [
        {
            label: 'Ventas (S/)',
            data: props.data.map((d) => d.sales),
            borderColor: theme.chartPrimary.value,
            backgroundColor: (context: { chart: { ctx: CanvasRenderingContext2D; height: number } }) => {
                const ctx = context.chart.ctx;
                const gradient = ctx.createLinearGradient(0, 0, 0, context.chart.height || 200);
                gradient.addColorStop(0, hslWithAlpha(theme.chartPrimary.value, 0.28));
                gradient.addColorStop(1, hslWithAlpha(theme.chartPrimary.value, 0));
                return gradient;
            },
            fill: true,
            tension: 0.42,
            pointRadius: 4,
            pointHoverRadius: 6,
            pointBackgroundColor: theme.background.value,
            pointBorderColor: theme.chartPrimary.value,
            pointBorderWidth: 2,
        },
    ],
}));

const chartOptions = computed<ChartOptions<'line'>>(() => ({
    responsive: true,
    maintainAspectRatio: false,
    interaction: {
        intersect: false,
        mode: 'index',
    },
    animation: {
        duration: 800,
        easing: 'easeOutQuart',
    },
    plugins: {
        legend: {
            display: false,
        },
        tooltip: {
            backgroundColor: theme.popover.value,
            titleColor: theme.popoverForeground.value,
            bodyColor: theme.popoverForeground.value,
            borderColor: theme.border.value,
            borderWidth: 1,
            padding: 12,
            displayColors: false,
            callbacks: {
                title: (items) => items[0]?.label ?? '',
                label: (context) => {
                    const value = context.parsed.y ?? 0;
                    const orders = props.data[context.dataIndex]?.orders ?? 0;

                    return [`S/ ${value.toFixed(2)}`, `${orders} pedido${orders === 1 ? '' : 's'}`];
                },
            },
        },
    },
    scales: {
        x: {
            grid: {
                display: false,
            },
            ticks: {
                color: theme.mutedForeground.value,
                font: {
                    size: 11,
                },
            },
        },
        y: {
            beginAtZero: true,
            grid: {
                color: hslWithAlpha(theme.border.value, 0.6),
            },
            ticks: {
                color: theme.mutedForeground.value,
                font: {
                    size: 11,
                },
                callback: (value) => `S/${value}`,
            },
        },
    },
}));

const totalSales = computed(() => props.data.reduce((sum, d) => sum + d.sales, 0));
const totalOrders = computed(() => props.data.reduce((sum, d) => sum + d.orders, 0));
const avgTicket = computed(() => (totalOrders.value > 0 ? totalSales.value / totalOrders.value : 0));
</script>

<template>
    <Card class="border border-border/50 shadow-sm">
        <CardHeader class="pb-2">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <CardTitle class="text-base font-semibold">Tendencia de Ventas</CardTitle>
                    <CardDescription>Últimos 7 días · Ingresos confirmados</CardDescription>
                </div>
                <div class="flex gap-6 sm:text-right">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">Total semana</p>
                        <p class="text-2xl font-bold tabular-nums">S/ {{ totalSales.toFixed(2) }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">Ticket prom.</p>
                        <p class="text-lg font-semibold tabular-nums">S/ {{ avgTicket.toFixed(2) }}</p>
                        <p class="text-xs text-muted-foreground">{{ totalOrders }} pedidos</p>
                    </div>
                </div>
            </div>
        </CardHeader>
        <CardContent class="pt-4">
            <div v-if="loading" class="flex h-[300px] items-center justify-center">
                <div class="h-40 w-full max-w-md animate-pulse rounded-xl bg-muted/60" />
            </div>
            <div v-else-if="data.length === 0" class="flex h-[300px] flex-col items-center justify-center text-center">
                <p class="text-sm font-medium text-muted-foreground">Sin datos de ventas recientes</p>
                <p class="mt-1 text-xs text-muted-foreground">Confirma pedidos en el pipeline para ver la tendencia</p>
            </div>
            <div v-else class="relative h-[300px] w-full">
                <Line v-if="chartReady" :key="theme.chartPrimary.value" :data="chartData" :options="chartOptions" />
            </div>
        </CardContent>
    </Card>
</template>
