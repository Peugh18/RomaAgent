<script setup lang="ts">
import { useChartTheme } from '@/composables/useChartTheme';
import { computed, nextTick, onMounted, ref } from 'vue';
import { Line } from 'vue-chartjs';
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Filler,
    type ChartOptions,
} from 'chart.js';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Filler);

const props = defineProps<{
    data: number[];
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
    labels: props.data.map((_, index) => index.toString()),
    datasets: [
        {
            data: props.data,
            borderColor: theme.chartPrimary.value,
            backgroundColor: (context: { chart: { ctx: CanvasRenderingContext2D; height: number } }) => {
                const ctx = context.chart.ctx;
                const gradient = ctx.createLinearGradient(0, 0, 0, context.chart.height || 40);
                gradient.addColorStop(0, hslWithAlpha(theme.chartPrimary.value, 0.35));
                gradient.addColorStop(1, hslWithAlpha(theme.chartPrimary.value, 0));
                return gradient;
            },
            fill: true,
            tension: 0.45,
            pointRadius: 0,
            borderWidth: 2,
        },
    ],
}));

const chartOptions = computed<ChartOptions<'line'>>(() => ({
    responsive: true,
    maintainAspectRatio: false,
    animation: {
        duration: 600,
        easing: 'easeOutQuart',
    },
    plugins: {
        legend: { display: false },
        tooltip: { enabled: false },
    },
    scales: {
        x: { display: false },
        y: { display: false },
    },
}));
</script>

<template>
    <div class="h-10 w-full">
        <div v-if="loading" class="h-full animate-pulse rounded-md bg-muted/60" />
        <Line v-else-if="chartReady && data.length > 0" :key="theme.chartPrimary.value" :data="chartData" :options="chartOptions" />
    </div>
</template>
