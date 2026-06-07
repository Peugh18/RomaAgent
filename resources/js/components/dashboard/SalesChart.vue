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
            borderColor: theme.primary.value,
            backgroundColor: (context: { chart: { ctx: CanvasRenderingContext2D; height: number } }) => {
                const ctx = context.chart.ctx;
                const gradient = ctx.createLinearGradient(0, 0, 0, context.chart.height || 200);
                gradient.addColorStop(0, hslWithAlpha(theme.primary.value, 0.25));
                gradient.addColorStop(1, hslWithAlpha(theme.primary.value, 0));
                return gradient;
            },
            fill: true,
            tension: 0.4,
            pointRadius: 4,
            pointBackgroundColor: theme.background.value,
            pointBorderColor: theme.primary.value,
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
                label: (context) => {
                    const value = context.parsed.y ?? 0;

                    return `S/ ${value.toFixed(2)}`;
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
                color: theme.border.value,
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
    <Card class="col-span-full border-0 shadow-sm lg:col-span-3">
        <CardHeader class="pb-2">
            <div class="flex items-start justify-between">
                <div>
                    <CardTitle class="text-base font-semibold">Tendencia de Ventas</CardTitle>
                    <CardDescription>Últimos 7 días · Ingresos reales confirmados</CardDescription>
                </div>
                <div class="text-right">
                    <div class="text-2xl font-bold">S/ {{ totalSales.toFixed(2) }}</div>
                    <div class="text-xs text-muted-foreground">
                        {{ totalOrders }} pedidos · Ticket promedio S/{{ avgTicket.toFixed(2) }}
                    </div>
                </div>
            </div>
        </CardHeader>
        <CardContent class="pt-4">
            <div v-if="loading" class="flex h-[280px] items-center justify-center">
                <div class="h-32 w-32 animate-pulse rounded-lg bg-muted" />
            </div>
            <div v-else-if="data.length === 0" class="flex h-[280px] items-center justify-center text-sm text-muted-foreground">
                Sin datos de ventas recientes
            </div>
            <div v-else class="relative h-[280px] w-full">
                <Line v-if="chartReady" :key="theme.primary.value" :data="chartData" :options="chartOptions" />
            </div>
        </CardContent>
    </Card>
</template>
