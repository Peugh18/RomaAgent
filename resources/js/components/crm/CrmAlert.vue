<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        variant?: 'error' | 'success' | 'warning';
    }>(),
    {
        variant: 'error',
    },
);

const classes = computed(() => {
    const map = {
        error: 'border-destructive/30 bg-destructive/10 text-destructive',
        success: 'border-emerald-500/30 bg-emerald-500/10 text-emerald-800 dark:text-emerald-200',
        warning: 'border-amber-500/30 bg-amber-500/10 text-amber-900 dark:text-amber-100',
    };

    return map[props.variant];
});
</script>

<template>
    <div class="rounded-lg border px-4 py-3 text-sm" :class="classes" role="alert">
        <slot />
    </div>
</template>
