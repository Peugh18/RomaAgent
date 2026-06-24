<script setup lang="ts">
import { Card, CardContent } from '@/components/ui/card';
import { useCurrency } from '@/composables/useCurrency';
import { type Sale, type SaleStatus } from '@/types/sale';
import { GripVertical, Eye, AlertCircle, BellRing, Loader2 } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
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

const isEstancada = computed(() => {
    if (props.status !== 'pago_pendiente') return false;
    // Usamos updated_at o created_at como fallback
    const dateStr = props.sale.updated_at || props.sale.created_at;
    if (!dateStr) return false;
    
    const diffMs = Date.now() - new Date(dateStr).getTime();
    return diffMs > 24 * 60 * 60 * 1000; // 24 horas
});

import { ref } from 'vue';
import { apiJson } from '@/composables/useApi';

const sendingReminder = ref(false);

const sendReminder = async () => {
    if (sendingReminder.value) return;
    sendingReminder.value = true;
    try {
        await apiJson(`/api/sales/${props.sale.id}/send-payment-reminder`, { method: 'POST' });
        alert('Recordatorio enviado exitosamente');
    } catch (e: any) {
        alert(e.message || 'Error al enviar recordatorio');
    } finally {
        sendingReminder.value = false;
    }
};
</script>

<template>
    <Card
        :class="[
            'border shadow-none transition-colors hover:bg-muted/50 cursor-pointer relative group',
            isEstancada ? 'border-red-400/50 bg-red-50/50 dark:bg-red-950/20 ring-1 ring-red-500/30' : borderClass,
            draggable ? 'cursor-grab active:cursor-grabbing' : '',
        ]"
        @click="!draggable && emit('openDetail', sale)"
    >
        <div class="absolute bottom-2 right-2 z-10 flex gap-1">
            <button
                v-if="isEstancada"
                type="button"
                class="flex h-6 w-6 items-center justify-center rounded-md bg-amber-100 text-amber-700 shadow-sm ring-1 ring-amber-300 transition-colors hover:bg-amber-200"
                title="Enviar recordatorio de pago"
                @click.stop="sendReminder"
            >
                <Loader2 v-if="sendingReminder" class="h-3.5 w-3.5 animate-spin" />
                <BellRing v-else class="h-3.5 w-3.5" />
            </button>
            <button
                v-if="draggable"
                type="button"
                class="flex h-6 w-6 items-center justify-center rounded-md bg-background/80 text-muted-foreground shadow-sm ring-1 ring-border transition-colors hover:bg-muted hover:text-foreground"
                title="Ver detalles"
                @click.stop="emit('openDetail', sale)"
            >
                <Eye class="h-3.5 w-3.5" />
            </button>
        </div>

        <CardContent class="p-2">
            <div class="flex items-center gap-1.5">
                <div class="min-w-0 flex-1 space-y-0.5">
                    <div class="flex items-center justify-between gap-1">
                        <div class="flex items-center gap-1.5 min-w-0 pr-1">
                            <AlertCircle v-if="isEstancada" class="h-3 w-3 shrink-0 text-red-500" />
                            <p class="truncate text-xs font-medium" :class="isEstancada ? 'text-red-700 dark:text-red-400' : ''">
                                {{ displayName(sale) }}
                            </p>
                        </div>
                        <span class="shrink-0 text-[11px] font-medium text-muted-foreground pr-6" :class="isEstancada ? 'text-red-600/70 dark:text-red-400/70' : ''">
                            {{ formatMoney(sale.total_amount) }}
                        </span>
                    </div>
                    <p class="truncate text-[11px] text-muted-foreground pr-6" :class="isEstancada ? 'text-red-600/70 dark:text-red-400/70' : ''">
                        {{ sale.product_name }}
                        <span v-if="sale.size && sale.size !== 'UNICA'" class="text-[11px] text-muted-foreground/70"> · {{ sale.size }}</span>
                        <span v-if="sale.color" class="text-[11px] text-muted-foreground/70"> · {{ sale.color }}</span>
                    </p>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
