<script setup lang="ts">
import { cn } from '@/lib/utils';
import { inject, type HTMLAttributes } from 'vue';

const props = defineProps<{
    value: string;
    class?: HTMLAttributes['class'];
}>();

const tabs = inject<{
    selectedTab: { value: string };
}>('tabs');

const isSelected = () => tabs?.selectedTab.value === props.value;
</script>

<template>
    <div
        v-if="isSelected()"
        role="tabpanel"
        :data-state="isSelected() ? 'active' : 'inactive'"
        :class="cn(
            'mt-2 ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
            props.class,
        )"
    >
        <slot />
    </div>
</template>
