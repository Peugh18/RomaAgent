<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import MiniSparkline from '@/components/dashboard/MiniSparkline.vue';
import NumberFlow from '@number-flow/vue';
import { ArrowDownRight, ArrowUpRight, type LucideIcon } from 'lucide-vue-next';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    title: string;
    value: string | number;
    icon: LucideIcon;
    subtitle?: string;
    trend?: number | null;
    trendLabel?: string;
    sparkline?: number[];
    loading?: boolean;
    variant?: 'default' | 'success' | 'warning' | 'danger';
    href?: string;
}>();

const variantStyles = computed(() => {
    const styles = {
        default: 'bg-slate-100 text-slate-600 dark:bg-slate-900/80 dark:text-slate-300',
        success: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400',
        warning: 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400',
        danger: 'bg-rose-100 text-rose-700 dark:bg-rose-950/40 dark:text-rose-400',
    };

    return styles[props.variant || 'default'];
});

const variantGlow = computed(() => {
    const styles = {
        default: 'from-slate-500/5 to-transparent',
        success: 'from-emerald-500/10 to-transparent',
        warning: 'from-amber-500/10 to-transparent',
        danger: 'from-rose-500/10 to-transparent',
    };

    return styles[props.variant || 'default'];
});

const trendPositive = computed(() => (props.trend ?? 0) >= 0);
const hasTrend = computed(() => props.trend !== undefined && props.trend !== null);
const isNumericValue = computed(() => typeof props.value === 'number' && Number.isFinite(props.value));
</script>

<template>
    <component
        :is="href ? Link : 'div'"
        :href="href"
        class="block h-full"
        :class="href ? 'rounded-xl transition-transform duration-200 hover:-translate-y-0.5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring' : ''"
    >
        <Card
            class="relative h-full overflow-hidden border border-border/50 shadow-sm transition-shadow duration-200 hover:shadow-md"
            :class="href ? 'cursor-pointer' : ''"
        >
            <div class="pointer-events-none absolute inset-0 bg-gradient-to-br opacity-80" :class="variantGlow" />

            <CardHeader class="relative flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle class="text-sm font-medium text-muted-foreground">
                    {{ title }}
                </CardTitle>
                <div class="rounded-xl p-2.5 shadow-sm" :class="variantStyles">
                    <component :is="icon" class="h-4 w-4" />
                </div>
            </CardHeader>

            <CardContent class="relative space-y-2">
                <div v-if="loading" class="space-y-2">
                    <Skeleton class="h-8 w-24" />
                    <Skeleton class="h-4 w-16" />
                </div>
                <template v-else>
                    <div class="space-y-1">
                        <div class="text-3xl font-bold tracking-tight tabular-nums">
                            <NumberFlow v-if="isNumericValue" :value="value as number" />
                            <span v-else>{{ value }}</span>
                        </div>
                        <p v-if="subtitle" class="text-xs text-muted-foreground">{{ subtitle }}</p>
                        <div v-if="hasTrend" class="flex items-center text-xs">
                            <component
                                :is="trendPositive ? ArrowUpRight : ArrowDownRight"
                                class="mr-1 h-3.5 w-3.5"
                                :class="trendPositive ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'"
                            />
                            <span :class="trendPositive ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'">
                                {{ Math.abs(trend!) }}%
                            </span>
                            <span class="ml-1 text-muted-foreground">{{ trendLabel ?? 'vs ayer' }}</span>
                        </div>
                    </div>

                    <MiniSparkline v-if="sparkline?.length" :data="sparkline" />
                </template>
            </CardContent>
        </Card>
    </component>
</template>
