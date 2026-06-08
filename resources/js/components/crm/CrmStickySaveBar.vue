<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { useSidebar } from '@/components/ui/sidebar';
import { Save } from 'lucide-vue-next';
import { computed } from 'vue';

withDefaults(
    defineProps<{
        saving?: boolean;
        saveLabel?: string;
        hint?: string;
    }>(),
    {
        saveLabel: 'Guardar cambios',
        hint: 'Los cambios se aplican al guardar.',
    },
);

defineEmits<{
    save: [];
}>();

const { state, isMobile } = useSidebar();

const barInset = computed(() => {
    if (isMobile.value) {
        return { left: '0px', right: '0px' };
    }

    const left = state.value === 'collapsed' ? '3rem' : '16rem';

    return { left, right: '0px' };
});
</script>

<template>
    <div
        class="fixed bottom-0 z-40 border-t border-border/60 bg-background/95 shadow-[0_-4px_24px_rgba(0,0,0,0.08)] backdrop-blur-md dark:shadow-[0_-4px_24px_rgba(0,0,0,0.35)]"
        :style="barInset"
    >
        <div class="mx-auto flex max-w-6xl flex-col-reverse gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
            <p class="hidden text-xs text-muted-foreground sm:block">{{ hint }}</p>
            <Button
                type="button"
                :disabled="saving"
                class="min-w-[140px] gap-2 bg-emerald-600 hover:bg-emerald-700 sm:ml-auto"
                @click="$emit('save')"
            >
                <Save class="h-4 w-4" />
                {{ saving ? 'Guardando…' : saveLabel }}
            </Button>
        </div>
    </div>
</template>
