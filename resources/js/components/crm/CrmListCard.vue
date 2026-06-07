<script setup lang="ts">
defineProps<{
    padding?: boolean;
}>();
</script>

<template>
    <div
        class="overflow-hidden rounded-xl border border-border/80 bg-card shadow-sm"
        :class="padding ? '' : ''"
    >
        <slot />
    </div>
</template>
