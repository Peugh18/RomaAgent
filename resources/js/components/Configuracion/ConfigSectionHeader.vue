<script setup lang="ts">
import type { Component } from 'vue';

defineProps<{
    title: string;
    description?: string;
    icon?: Component;
}>();
</script>

<template>
    <div class="mb-5 flex items-start gap-3 border-b border-border/60 pb-4">
        <div
            v-if="icon"
            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-emerald-500/10"
        >
            <component :is="icon" class="h-4 w-4 text-emerald-500" />
        </div>
        <div class="min-w-0 flex-1">
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-base font-semibold tracking-tight text-foreground">{{ title }}</h3>
                <slot name="actions" />
            </div>
            <p v-if="description" class="mt-0.5 text-xs text-muted-foreground">{{ description }}</p>
        </div>
    </div>
</template>
