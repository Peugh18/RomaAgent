<script setup lang="ts">
import { provide, ref, watch } from 'vue';

const props = defineProps<{
    modelValue?: string;
    defaultValue?: string;
}>();

const emits = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

const selectedTab = ref(props.modelValue ?? props.defaultValue ?? '');

watch(() => props.modelValue, (newValue) => {
    if (newValue !== undefined) {
        selectedTab.value = newValue;
    }
});

watch(selectedTab, (newValue) => {
    emits('update:modelValue', newValue);
});

provide('tabs', {
    selectedTab,
    setSelectedTab: (value: string) => {
        selectedTab.value = value;
    },
});
</script>

<template>
    <div class="w-full">
        <slot />
    </div>
</template>
