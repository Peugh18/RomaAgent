<script setup lang="ts">
import { Card, CardContent } from '@/components/ui/card';
import { useCurrency } from '@/composables/useCurrency';
import { type Sale, type SaleStatus } from '@/types/sale';
import { GripVertical } from 'lucide-vue-next';

defineProps<{
    sale: Sale;
    status: SaleStatus;
    draggable?: boolean;
    borderClass?: string;
}>();

const emit = defineEmits<{
    openDetail: [sale: Sale];
}>();

const { format: formatMoney } = useCurrency();

const displayName = (sale: Sale) =>
    sale.customer_name ?? sale.customer?.name ?? sale.phone_number;
</script>

<template>
    <Card
        :class="[
            'border shadow-none transition-colors hover:bg-muted/50 cursor-pointer',
            borderClass,
            draggable ? 'cursor-grab active:cursor-grabbing' : '',
        ]"
        @click="!draggable && emit('openDetail', sale)"
    >
        <CardContent class="p-2">
            <div class="flex items-center gap-1.5">
                <GripVertical
                    v-if="draggable"
                    class="h-3.5 w-3.5 shrink-0 text-muted-foreground/40"
                />
                <div class="min-w-0 flex-1 space-y-0.5">
                    <div class="flex items-center justify-between gap-1">
                        <p class="truncate text-xs font-medium">
                            {{ displayName(sale) }}
                        </p>
                        <span class="shrink-0 text-[10px] text-muted-foreground">
                            {{ formatMoney(sale.total_amount) }}
                        </span>
                    </div>
                    <p class="truncate text-[10px] text-muted-foreground">
                        {{ sale.product_name }}
                        <span v-if="sale.color"> · {{ sale.color }}</span>
                        <span v-if="sale.size && sale.size !== 'UNICA'"> · {{ sale.size }}</span>
                    </p>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
