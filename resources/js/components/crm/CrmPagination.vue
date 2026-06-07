<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { ChevronLeft, ChevronRight, MoreHorizontal } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    page: number;
    lastPage: number;
    total?: number;
    from?: number;
    to?: number;
    disabled?: boolean;
}>();

const emit = defineEmits<{
    'update:page': [page: number];
}>();

const summary = computed(() => {
    if (props.from !== undefined && props.to !== undefined && props.total !== undefined) {
        return `Mostrando ${props.from}–${props.to} de ${props.total}`;
    }

    if (props.total !== undefined) {
        return `Página ${props.page} de ${props.lastPage} · ${props.total} en total`;
    }

    return `Página ${props.page} de ${props.lastPage}`;
});

const pageItems = computed((): Array<number | 'ellipsis'> => {
    const last = props.lastPage;
    const current = props.page;

    if (last <= 1) {
        return [1];
    }

    if (last <= 7) {
        return Array.from({ length: last }, (_, index) => index + 1);
    }

    const items: Array<number | 'ellipsis'> = [1];

    if (current > 3) {
        items.push('ellipsis');
    }

    const start = Math.max(2, current - 1);
    const end = Math.min(last - 1, current + 1);

    for (let page = start; page <= end; page++) {
        items.push(page);
    }

    if (current < last - 2) {
        items.push('ellipsis');
    }

    items.push(last);

    return items;
});

const goTo = (page: number) => {
    if (page < 1 || page > props.lastPage || page === props.page || props.disabled) {
        return;
    }

    emit('update:page', page);
};
</script>

<template>
    <div
        v-if="lastPage > 1 || (total !== undefined && total > 0)"
        class="flex flex-col gap-3 border-t px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
    >
        <p class="text-sm text-muted-foreground">
            {{ summary }}
        </p>
        <div v-if="lastPage > 1" class="flex items-center justify-end gap-1">
            <Button
                variant="outline"
                size="icon"
                class="h-8 w-8"
                :disabled="disabled || page <= 1"
                @click="goTo(page - 1)"
            >
                <ChevronLeft class="h-4 w-4" />
            </Button>
            <template v-for="(item, index) in pageItems" :key="`${item}-${index}`">
                <span
                    v-if="item === 'ellipsis'"
                    class="flex h-8 w-8 items-center justify-center text-muted-foreground"
                >
                    <MoreHorizontal class="h-4 w-4" />
                </span>
                <Button
                    v-else
                    size="sm"
                    class="h-8 min-w-8 px-2"
                    :variant="page === item ? 'default' : 'outline'"
                    :disabled="disabled"
                    @click="goTo(item)"
                >
                    {{ item }}
                </Button>
            </template>
            <Button
                variant="outline"
                size="icon"
                class="h-8 w-8"
                :disabled="disabled || page >= lastPage"
                @click="goTo(page + 1)"
            >
                <ChevronRight class="h-4 w-4" />
            </Button>
        </div>
    </div>
</template>
