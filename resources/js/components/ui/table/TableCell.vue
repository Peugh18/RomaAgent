<script setup lang="ts">
import { cn } from '@/lib/utils';
import type { HTMLAttributes } from 'vue';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <td :class="cn('py-2.5 px-4 align-middle [&:has([role=checkbox])]:pr-0', props.class)">
        <slot />
    </td>
</template>
