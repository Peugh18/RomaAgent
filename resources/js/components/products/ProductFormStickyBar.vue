<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { useSidebar } from '@/components/ui/sidebar';
import { Link } from '@inertiajs/vue3';
import { Save } from 'lucide-vue-next';
import { computed } from 'vue';

withDefaults(
    defineProps<{
        formId: string;
        cancelHref?: string;
        saving?: boolean;
        saveLabel?: string;
        hint?: string;
    }>(),
    {
        cancelHref: '/productos',
        saveLabel: 'Guardar producto',
        hint: 'Los cambios se reflejan en el catálogo y en el prompt de la IA al guardar.',
    },
);

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
            <div class="flex items-center justify-end gap-3">
                <Button variant="outline" as-child class="border-border/70">
                    <Link :href="cancelHref">Cancelar</Link>
                </Button>
                <Button
                    type="submit"
                    :form="formId"
                    :disabled="saving"
                    class="min-w-[140px] gap-2 bg-emerald-600 hover:bg-emerald-700"
                >
                    <Save class="h-4 w-4" />
                    {{ saving ? 'Guardando…' : saveLabel }}
                </Button>
            </div>
        </div>
    </div>
</template>
