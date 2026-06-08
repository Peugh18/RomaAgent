<script setup lang="ts">
import NumberFlow from '@number-flow/vue';
import type { Component } from 'vue';
import { computed } from 'vue';

export type CrmHeroVariant = 'emerald' | 'blue' | 'violet' | 'amber' | 'sky';

export interface CrmHeroStat {
    label: string;
    value: string | number;
}

const props = withDefaults(
    defineProps<{
        title: string;
        description?: string;
        icon?: Component;
        variant?: CrmHeroVariant;
        compact?: boolean;
        stats?: CrmHeroStat[];
    }>(),
    {
        variant: 'emerald',
        compact: false,
    },
);

const variantStyles: Record<
    CrmHeroVariant,
    { border: string; gradient: string; iconWrap: string; iconText: string; blur: string; statText: string }
> = {
    emerald: {
        border: 'border-emerald-500/20',
        gradient: 'from-emerald-500/10',
        iconWrap: 'bg-emerald-500/10',
        iconText: 'text-emerald-500',
        blur: 'bg-emerald-500/10',
        statText: 'text-emerald-600 dark:text-emerald-400',
    },
    blue: {
        border: 'border-blue-500/20',
        gradient: 'from-blue-500/10',
        iconWrap: 'bg-blue-500/10',
        iconText: 'text-blue-500',
        blur: 'bg-blue-500/10',
        statText: 'text-blue-600 dark:text-blue-400',
    },
    violet: {
        border: 'border-violet-500/20',
        gradient: 'from-violet-500/10',
        iconWrap: 'bg-violet-500/10',
        iconText: 'text-violet-500',
        blur: 'bg-violet-500/10',
        statText: 'text-violet-600 dark:text-violet-400',
    },
    amber: {
        border: 'border-amber-500/20',
        gradient: 'from-amber-500/10',
        iconWrap: 'bg-amber-500/10',
        iconText: 'text-amber-500',
        blur: 'bg-amber-500/10',
        statText: 'text-amber-600 dark:text-amber-400',
    },
    sky: {
        border: 'border-sky-500/20',
        gradient: 'from-sky-500/10',
        iconWrap: 'bg-sky-500/10',
        iconText: 'text-sky-500',
        blur: 'bg-sky-500/10',
        statText: 'text-sky-600 dark:text-sky-400',
    },
};

const theme = computed(() => variantStyles[props.variant]);

const isNumeric = (value: string | number): boolean => typeof value === 'number' && Number.isFinite(value);
</script>

<template>
    <div
        v-motion
        :initial="{ opacity: 0, y: 12 }"
        :enter="{ opacity: 1, y: 0, transition: { duration: 320, ease: 'easeOut' } }"
        class="relative overflow-hidden rounded-2xl border bg-gradient-to-br via-card to-card shadow-sm"
        :class="[theme.border, theme.gradient, compact ? 'p-4 sm:p-5' : 'p-5 sm:p-6']"
    >
        <div class="pointer-events-none absolute -right-6 -top-6 h-32 w-32 rounded-full blur-3xl" :class="theme.blur" />

        <div class="relative flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="flex min-w-0 gap-3 sm:gap-4">
                <div
                    v-if="icon"
                    class="flex shrink-0 items-center justify-center rounded-xl shadow-sm"
                    :class="[theme.iconWrap, compact ? 'h-10 w-10' : 'h-11 w-11']"
                >
                    <component :is="icon" :class="[theme.iconText, compact ? 'h-5 w-5' : 'h-5 w-5']" />
                </div>

                <div class="min-w-0 space-y-1">
                    <h1 :class="compact ? 'text-xl font-bold tracking-tight' : 'text-2xl font-bold tracking-tight sm:text-3xl'">
                        {{ title }}
                    </h1>
                    <p v-if="description" class="max-w-2xl text-sm text-muted-foreground">
                        {{ description }}
                    </p>

                    <div v-if="stats?.length" class="flex flex-wrap gap-2 pt-2">
                        <div
                            v-for="stat in stats"
                            :key="stat.label"
                            class="inline-flex items-center gap-2 rounded-full border border-border/60 bg-background/60 px-3 py-1 text-xs backdrop-blur-sm"
                        >
                            <span class="text-muted-foreground">{{ stat.label }}:</span>
                            <NumberFlow v-if="isNumeric(stat.value)" :value="stat.value as number" class="font-semibold" />
                            <span v-else class="font-semibold">{{ stat.value }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="$slots.actions" class="flex shrink-0 flex-wrap items-center gap-2">
                <slot name="actions" />
            </div>
        </div>
    </div>
</template>
