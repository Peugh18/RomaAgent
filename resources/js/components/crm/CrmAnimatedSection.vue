<script setup lang="ts">
withDefaults(
    defineProps<{
        delay?: number;
    }>(),
    {
        delay: 0,
    },
);
</script>

<template>
    <div
        v-motion
        :initial="{ opacity: 0, y: 12 }"
        :enter="{ opacity: 1, y: 0, transition: { delay, duration: 300, ease: 'easeOut' } }"
    >
        <slot />
    </div>
</template>
