<script setup lang="ts">
defineProps<{
    title?: string;
    description?: string;
    noPadding?: boolean;
}>();
</script>

<template>
    <div class="overflow-hidden rounded-2xl border border-border/50 bg-card shadow-sm transition-shadow duration-200">
        <div
            v-if="title || $slots.header"
            class="flex items-center justify-between border-b border-border/60 bg-muted/20 px-5 py-4"
        >
            <div v-if="title">
                <h3 class="text-sm font-semibold text-foreground">{{ title }}</h3>
                <p v-if="description" class="mt-0.5 text-xs text-muted-foreground">{{ description }}</p>
            </div>
            <slot name="header" />
        </div>
        <div :class="noPadding ? '' : 'p-5'">
            <slot />
        </div>
    </div>
</template>
