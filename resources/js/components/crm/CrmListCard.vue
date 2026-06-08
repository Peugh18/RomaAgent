<script setup lang="ts">
defineProps<{
    padding?: boolean;
}>();
</script>

<template>
    <div
        class="overflow-hidden rounded-2xl border border-border/50 bg-card shadow-sm transition-shadow duration-200 hover:shadow-md"
        :class="padding ? '' : ''"
    >
        <slot />
    </div>
</template>
