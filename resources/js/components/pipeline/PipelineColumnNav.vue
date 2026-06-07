<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { SALE_STATUS_LABELS, type SaleStatus } from '@/types/sale';
import { computed } from 'vue';

const props = defineProps<{
    columns: SaleStatus[];
    counts: Record<SaleStatus, number>;
    activeStatus?: SaleStatus | null;
}>();

const emit = defineEmits<{
    navigate: [status: SaleStatus];
}>();

const items = computed(() =>
    props.columns
        .map((status) => ({
            status,
            label: SALE_STATUS_LABELS[status],
            count: props.counts[status] ?? 0,
        }))
        .filter((item) => item.count > 0 || ['pago_pendiente', 'pago_recibido', 'confirmado', 'enviado', 'entregado'].includes(item.status)),
);
</script>

<template>
    <div class="flex gap-2 overflow-x-auto pb-1 scrollbar-thin">
        <Button
            v-for="item in items"
            :key="item.status"
            variant="outline"
            size="sm"
            class="h-8 shrink-0 gap-2 text-xs"
            :class="activeStatus === item.status ? 'border-primary bg-primary/5' : ''"
            @click="emit('navigate', item.status)"
        >
            {{ item.label }}
            <Badge variant="secondary" class="h-5 min-w-5 px-1.5 font-mono text-[10px]">
                {{ item.count }}
            </Badge>
        </Button>
    </div>
</template>
