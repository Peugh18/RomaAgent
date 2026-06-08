<script setup lang="ts">
import type { Component } from 'vue';

defineProps<{
    title: string;
    description?: string;
    icon?: Component;
}>();
</script>

<template>
    <div class="flex flex-col items-center justify-center px-6 py-16 text-center">
        <div v-if="icon || $slots.icon" class="rounded-2xl bg-muted/80 p-4 shadow-inner">
            <slot name="icon">
                <component :is="icon" class="h-8 w-8 text-muted-foreground" />
            </slot>
        </div>
        <h3 class="mt-4 text-lg font-semibold tracking-tight">{{ title }}</h3>
        <p v-if="description" class="mt-2 max-w-md text-sm text-muted-foreground">{{ description }}</p>
        <div v-if="$slots.actions" class="mt-5 flex flex-wrap justify-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
