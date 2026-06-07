<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { ArrowDownRight, ArrowUpRight, type LucideIcon } from 'lucide-vue-next';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    title: string;
    value: string | number;
    icon: LucideIcon;
    trend?: number;
    trendLabel?: string;
    loading?: boolean;
    variant?: 'default' | 'success' | 'warning' | 'danger';
    href?: string;
}>();

const variantStyles = computed(() => {
    const styles = {
        default: 'bg-slate-50 text-slate-600 dark:bg-slate-950 dark:text-slate-400',
        success: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/30 dark:text-emerald-400',
        warning: 'bg-amber-50 text-amber-600 dark:bg-amber-950/30 dark:text-amber-400',
        danger: 'bg-rose-50 text-rose-600 dark:bg-rose-950/30 dark:text-rose-400',
    };
    return styles[props.variant || 'default'];
});

const trendPositive = computed(() => (props.trend ?? 0) >= 0);
</script>

<template>
    <component
        :is="href ? Link : 'div'"
        :href="href"
        class="block"
        :class="href ? 'rounded-xl transition-opacity hover:opacity-90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring' : ''"
    >
        <Card class="overflow-hidden border-0 shadow-sm" :class="href ? 'cursor-pointer' : ''">
            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle class="text-sm font-medium text-muted-foreground">
                    {{ title }}
                </CardTitle>
                <div class="rounded-lg p-2" :class="variantStyles">
                    <component :is="icon" class="h-4 w-4" />
                </div>
            </CardHeader>
            <CardContent>
                <div v-if="loading" class="space-y-2">
                    <Skeleton class="h-7 w-24" />
                    <Skeleton class="h-4 w-16" />
                </div>
                <div v-else class="space-y-1">
                    <div class="text-2xl font-bold tracking-tight">
                        {{ value }}
                    </div>
                    <div v-if="trend !== undefined" class="flex items-center text-xs">
                        <component
                            :is="trendPositive ? ArrowUpRight : ArrowDownRight"
                            class="mr-1 h-3.5 w-3.5"
                            :class="trendPositive ? 'text-emerald-600' : 'text-rose-600'"
                        />
                        <span :class="trendPositive ? 'text-emerald-600' : 'text-rose-600'">
                            {{ Math.abs(trend) }}%
                        </span>
                        <span class="ml-1 text-muted-foreground">
                            {{ trendLabel }}
                        </span>
                    </div>
                </div>
            </CardContent>
        </Card>
    </component>
</template>
