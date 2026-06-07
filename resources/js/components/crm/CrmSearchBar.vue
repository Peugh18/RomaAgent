<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { Search, X } from 'lucide-vue-next';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        modelValue: string;
        placeholder?: string;
        disabled?: boolean;
        class?: string;
    }>(),
    {
        placeholder: 'Buscar…',
        disabled: false,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const hasValue = computed(() => props.modelValue.trim().length > 0);

const clear = () => {
    emit('update:modelValue', '');
};
</script>

<template>
    <div class="relative w-full max-w-sm" :class="props.class">
        <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
        <Input
            :model-value="modelValue"
            :placeholder="placeholder"
            :disabled="disabled"
            class="h-10 pl-9 pr-9"
            @update:model-value="emit('update:modelValue', String($event))"
        />
        <button
            v-if="hasValue"
            type="button"
            class="absolute right-2 top-1/2 -translate-y-1/2 rounded-md p-1 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
            aria-label="Limpiar búsqueda"
            @click="clear"
        >
            <X class="h-4 w-4" />
        </button>
    </div>
</template>
