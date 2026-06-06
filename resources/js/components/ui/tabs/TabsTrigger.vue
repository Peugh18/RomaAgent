<script setup lang="ts">
import { cn } from '@/lib/utils';
import { inject, type HTMLAttributes } from 'vue';

const props = defineProps<{
    value: string;
    class?: HTMLAttributes['class'];
}>();

const tabs = inject<{
    selectedTab: { value: string };
    setSelectedTab: (value: string) => void;
}>('tabs');

const isSelected = () => tabs?.selectedTab.value === props.value;

const handleClick = () => {
    tabs?.setSelectedTab(props.value);
};
</script>

<template>
    <button
        type="button"
        role="tab"
        :aria-selected="isSelected()"
        :data-state="isSelected() ? 'active' : 'inactive'"
        :class="cn(
            'inline-flex items-center justify-center whitespace-nowrap rounded-sm px-3 py-1.5 text-sm font-medium ring-offset-background transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 data-[state=active]:bg-background data-[state=active]:text-foreground data-[state=active]:shadow-sm',
            props.class,
        )"
        @click="handleClick"
    >
        <slot />
    </button>
</template>
