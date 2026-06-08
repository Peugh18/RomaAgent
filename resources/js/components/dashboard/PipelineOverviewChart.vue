<script setup lang="ts">
import { useChartTheme } from '@/composables/useChartTheme';
import { saleStatusChartColor } from '@/lib/dashboardStatusColors';
import { SALE_STATUS_LABELS, type SaleStatus } from '@/types/sale';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Kanban } from 'lucide-vue-next';
import { computed, nextTick, onMounted, ref } from 'vue';
import { Doughnut } from 'vue-chartjs';
import {
    ArcElement,
    Chart as ChartJS,
    Legend,
    Tooltip,
    type ChartOptions,
} from 'chart.js';

ChartJS.register(ArcElement, Tooltip, Legend);

interface PipelineSlice {
    status: string;
    label: string;
    count: number;
}

const props = defineProps<{
    data: PipelineSlice[];
    loading?: boolean;
}>();

const theme = useChartTheme();
const chartReady = ref(false);

onMounted(async () => {
    theme.refresh();
    await nextTick();
    chartReady.value = true;
});

const total = computed(() => props.data.reduce((sum, item) => sum + item.count, 0));

const chartData = computed(() => ({
    labels: props.data.map((item) => item.label),
    datasets: [
        {
            data: props.data.map((item) => item.count),
            backgroundColor: props.data.map((item) => saleStatusChartColor(item.status)),
            borderColor: theme.background.value,
            borderWidth: 2,
            hoverOffset: 6,
        },
    ],
}));

const chartOptions = computed<ChartOptions<'doughnut'>>(() => ({
    responsive: true,
    maintainAspectRatio: false,
    cutout: '68%',
    animation: {
        animateRotate: true,
        duration: 700,
        easing: 'easeOutQuart',
    },
    plugins: {
        legend: { display: false },
        tooltip: {
            backgroundColor: theme.popover.value,
            titleColor: theme.popoverForeground.value,
            bodyColor: theme.popoverForeground.value,
            borderColor: theme.border.value,
            borderWidth: 1,
            padding: 10,
            callbacks: {
                label: (context) => {
                    const value = context.parsed ?? 0;
                    const pct = total.value > 0 ? Math.round((value / total.value) * 100) : 0;

                    return ` ${value} pedidos (${pct}%)`;
                },
            },
        },
    },
}));
</script>

<template>
    <Card class="border border-border/50 shadow-sm">
        <CardHeader class="pb-2">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div class="rounded-lg bg-emerald-500/10 p-2">
                        <Kanban class="h-4 w-4 text-emerald-500" />
                    </div>
                    <div>
                        <CardTitle class="text-base font-semibold">Pipeline activo</CardTitle>
                        <CardDescription>Distribución por estado</CardDescription>
                    </div>
                </div>
                <Link
                    href="/pipeline"
                    class="inline-flex items-center gap-1 text-xs font-medium text-emerald-600 transition hover:text-emerald-500 dark:text-emerald-400"
                >
                    Ver todo
                    <ArrowRight class="h-3.5 w-3.5" />
                </Link>
            </div>
        </CardHeader>
        <CardContent>
            <div v-if="loading" class="space-y-4">
                <Skeleton class="mx-auto h-44 w-44 rounded-full" />
                <Skeleton class="h-4 w-full" />
            </div>

            <div v-else-if="data.length === 0" class="flex h-52 flex-col items-center justify-center text-center">
                <p class="text-sm text-muted-foreground">Sin pedidos en el pipeline</p>
                <p class="mt-1 text-xs text-muted-foreground">Los pedidos aparecerán cuando la IA cierre ventas</p>
            </div>

            <div v-else class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)] sm:items-center">
                <div class="relative mx-auto h-44 w-44 sm:mx-0">
                    <Doughnut v-if="chartReady" :key="total" :data="chartData" :options="chartOptions" />
                    <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                        <span class="text-2xl font-bold tabular-nums">{{ total }}</span>
                        <span class="text-[11px] uppercase tracking-wide text-muted-foreground">pedidos</span>
                    </div>
                </div>

                <ul class="space-y-2">
                    <li
                        v-for="item in data.slice(0, 6)"
                        :key="item.status"
                        class="flex items-center justify-between gap-2 rounded-lg px-2 py-1.5 text-sm transition hover:bg-muted/40"
                    >
                        <div class="flex min-w-0 items-center gap-2">
                            <span
                                class="h-2.5 w-2.5 shrink-0 rounded-full"
                                :style="{ backgroundColor: saleStatusChartColor(item.status) }"
                            />
                            <span class="truncate text-muted-foreground">
                                {{ SALE_STATUS_LABELS[item.status as SaleStatus] ?? item.label }}
                            </span>
                        </div>
                        <span class="shrink-0 font-semibold tabular-nums">{{ item.count }}</span>
                    </li>
                </ul>
            </div>
        </CardContent>
    </Card>
</template>
