<script setup lang="ts">
import { type Component } from 'vue';

defineProps<{
    title: string;
    description: string;
    icon: Component;
    bullets?: string[];
}>();
</script>

<template>
    <div class="flex flex-1 flex-col gap-6 rounded-xl border border-dashed border-border bg-card/50 p-8">
        <div class="flex items-start gap-4">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                <component :is="icon" class="h-6 w-6" />
            </div>
            <div class="space-y-1">
                <h2 class="text-xl font-semibold tracking-tight">{{ title }}</h2>
                <p class="max-w-2xl text-sm text-muted-foreground">{{ description }}</p>
            </div>
        </div>

        <ul v-if="bullets?.length" class="grid gap-2 sm:grid-cols-2">
            <li
                v-for="bullet in bullets"
                :key="bullet"
                class="rounded-lg border border-border bg-background px-4 py-3 text-sm text-muted-foreground"
            >
                {{ bullet }}
            </li>
        </ul>

        <p class="text-xs text-muted-foreground/80">Módulo en construcción — sin conexión a flujos por ahora.</p>
    </div>
</template>
