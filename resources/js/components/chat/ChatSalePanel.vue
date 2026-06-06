<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { useCurrency } from '@/composables/useCurrency';
import { SALE_STATUS_LABELS, saleCanConfirmPayment, type Sale } from '@/types/sale';
import { CheckCircle, Loader2, Package } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    sale: Sale | null;
    loading: boolean;
    confirming: boolean;
    error: string | null;
}>();

const emit = defineEmits<{
    confirmPayment: [];
    refresh: [];
}>();

const { format: formatMoney } = useCurrency();

const statusLabel = computed(() =>
    props.sale ? SALE_STATUS_LABELS[props.sale.status] : '',
);

const canConfirm = computed(() =>
    props.sale ? saleCanConfirmPayment(props.sale.status) : false,
);
</script>

<template>
    <div
        v-if="loading"
        class="flex items-center gap-2 border-b border-border bg-muted/30 px-4 py-2 text-xs text-muted-foreground"
    >
        <Loader2 class="h-3.5 w-3.5 animate-spin" />
        Cargando pedido…
    </div>

    <div
        v-else-if="sale"
        class="border-b border-border bg-card px-4 py-3"
    >
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0 space-y-1">
                <div class="flex items-center gap-2 text-sm font-semibold text-foreground">
                    <Package class="h-4 w-4 shrink-0 text-primary" />
                    <span class="truncate">{{ sale.product_name }}</span>
                    <span v-if="sale.color" class="font-normal text-muted-foreground">· {{ sale.color }}</span>
                </div>
                <p class="text-xs text-muted-foreground">
                    {{ statusLabel }}
                    <span v-if="sale.payment_method"> · {{ sale.payment_method }}</span>
                    <span v-if="sale.delivery_district"> · {{ sale.delivery_district }}</span>
                </p>
                <p class="text-sm font-medium text-foreground">
                    Total: {{ formatMoney(sale.total_amount) }}
                    <span class="text-xs font-normal text-muted-foreground">
                        (producto {{ formatMoney(sale.unit_price) }} + envío {{ formatMoney(sale.delivery_cost) }})
                    </span>
                </p>
            </div>

            <div class="flex shrink-0 gap-2">
                <Button
                    v-if="canConfirm"
                    size="sm"
                    class="gap-1.5"
                    :disabled="confirming"
                    @click="emit('confirmPayment')"
                >
                    <CheckCircle class="h-4 w-4" />
                    {{ confirming ? 'Confirmando…' : 'Confirmar pago' }}
                </Button>
                <Button size="sm" variant="outline" @click="emit('refresh')">
                    Actualizar
                </Button>
            </div>
        </div>

        <p v-if="error" class="mt-2 text-xs text-red-600 dark:text-red-400">{{ error }}</p>

        <p v-if="canConfirm" class="mt-2 text-xs text-amber-700 dark:text-amber-400">
            Revisa el comprobante en el chat y confirma para registrar la venta y bajar stock.
        </p>
    </div>
</template>
