<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { useCurrency } from '@/composables/useCurrency';
import {
    SALE_STATUS_LABELS,
    saleCanConfirmPayment,
    saleCanMarkDelivered,
    saleCanMarkShipped,
    saleEsPagoTarjeta,
    salePaymentHint,
    saleVerifyPaymentLabel,
    type Sale,
    type SaleTransition,
} from '@/types/sale';
import { CheckCircle, ExternalLink, Loader2, MapPin, Package, Truck } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { apiJson, getCsrfToken } from '@/composables/useApi';

const props = defineProps<{
    sale: Sale | null;
    loading: boolean;
    transitioning: boolean;
    error: string | null;
}>();

const emit = defineEmits<{
    openTransition: [transition: SaleTransition];
    refresh: [];
    linkSent: [];
}>();

const sendingLink = ref(false);
const linkError = ref<string | null>(null);

const esTarjetaPendiente = computed(
    () => props.sale !== null && saleEsPagoTarjeta(props.sale) && props.sale.status === 'pago_pendiente',
);

const enviarLinkPago = async () => {
    if (!props.sale || sendingLink.value) {
        return;
    }

    sendingLink.value = true;
    linkError.value = null;

    try {
        await apiJson(`/api/sales/${props.sale.id}/send-payment-link`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify({}),
        });
        emit('linkSent');
        emit('refresh');
    } catch (err) {
        linkError.value = err instanceof Error ? err.message : 'No se pudo enviar el link';
    } finally {
        sendingLink.value = false;
    }
};

const { format: formatMoney } = useCurrency();

const statusLabel = computed(() =>
    props.sale ? SALE_STATUS_LABELS[props.sale.status] : '',
);

const canConfirm = computed(() =>
    props.sale ? saleCanConfirmPayment(props.sale) : false,
);

const canShip = computed(() =>
    props.sale ? saleCanMarkShipped(props.sale) : false,
);

const canDeliver = computed(() =>
    props.sale ? saleCanMarkDelivered(props.sale) : false,
);

const confirmLabel = computed(() =>
    props.sale ? saleVerifyPaymentLabel(props.sale) : 'Confirmar pago',
);

const hint = computed(() =>
    props.sale ? salePaymentHint(props.sale) : '',
);

const customerName = computed(() =>
    props.sale?.customer?.name
    ?? props.sale?.customer_data?.nombre
    ?? props.sale?.customer_data?.name
    ?? null,
);

const deliveryAddress = computed(() =>
    props.sale?.customer_data?.direccion
    ?? props.sale?.customer_data?.address
    ?? props.sale?.customer_data?.ubicacion_actual
    ?? null,
);

const mapsUrl = computed(() => props.sale?.customer_data?.maps_url ?? null);
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
                    <span class="truncate">{{ sale.quantity }}x {{ sale.product_name }}</span>
                    <span v-if="sale.size" class="font-normal text-muted-foreground">· {{ sale.size }}</span>
                    <span v-if="sale.color" class="font-normal text-muted-foreground">· {{ sale.color }}</span>
                </div>
                <p class="text-xs text-muted-foreground">
                    <span v-if="customerName">{{ customerName }} · </span>
                    {{ statusLabel }}
                    <span v-if="sale.payment_method"> · {{ sale.payment_method }}</span>
                    <span v-if="sale.delivery_district"> · {{ sale.delivery_district }}</span>
                </p>
                <p v-if="deliveryAddress" class="text-xs text-muted-foreground">
                    {{ deliveryAddress }}
                    <a
                        v-if="mapsUrl"
                        :href="mapsUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="ml-1 inline-flex items-center gap-0.5 text-primary hover:underline"
                    >
                        <MapPin class="h-3 w-3" />
                        GPS
                    </a>
                </p>
                <p class="text-sm font-medium text-foreground">
                    Total: {{ formatMoney(sale.total_amount) }}
                    <span class="text-xs font-normal text-muted-foreground">
                        (producto {{ formatMoney(sale.unit_price) }} + envío {{ formatMoney(sale.delivery_cost) }})
                    </span>
                </p>
            </div>

            <div class="flex shrink-0 flex-wrap gap-2">
                <Button
                    v-if="esTarjetaPendiente"
                    size="sm"
                    variant="secondary"
                    class="gap-1.5"
                    :disabled="sendingLink"
                    @click="enviarLinkPago"
                >
                    <Loader2 v-if="sendingLink" class="h-4 w-4 animate-spin" />
                    <ExternalLink v-else class="h-4 w-4" />
                    {{ sendingLink ? 'Enviando…' : 'Enviar link de pago' }}
                </Button>
                <Button
                    v-if="canConfirm"
                    size="sm"
                    class="gap-1.5"
                    :disabled="transitioning"
                    @click="emit('openTransition', 'confirm_payment')"
                >
                    <CheckCircle class="h-4 w-4" />
                    {{ transitioning ? 'Procesando…' : confirmLabel }}
                </Button>
                <Button
                    v-if="canShip"
                    size="sm"
                    variant="secondary"
                    class="gap-1.5"
                    :disabled="transitioning"
                    @click="emit('openTransition', 'mark_shipped')"
                >
                    <Truck class="h-4 w-4" />
                    Marcar enviado
                </Button>
                <Button
                    v-if="canDeliver"
                    size="sm"
                    class="gap-1.5 bg-emerald-600 hover:bg-emerald-700"
                    :disabled="transitioning"
                    @click="emit('openTransition', 'mark_delivered')"
                >
                    <CheckCircle class="h-4 w-4" />
                    Marcar entregado
                </Button>
                <Button size="sm" variant="outline" @click="emit('refresh')">
                    Actualizar
                </Button>
            </div>
        </div>

        <p v-if="error" class="mt-2 text-xs text-red-600 dark:text-red-400">{{ error }}</p>

        <p v-if="linkError" class="mt-2 text-xs text-red-600 dark:text-red-400">{{ linkError }}</p>

        <p v-if="esTarjetaPendiente" class="mt-2 text-xs text-indigo-700 dark:text-indigo-300">
            Envía el link con un clic. Cuando la clienta pague y mande comprobante, verifica y confirma.
        </p>

        <p v-if="canConfirm" class="mt-2 text-xs text-amber-700 dark:text-amber-400">
            {{ hint }}
        </p>
        <p v-else-if="sale && sale.status === 'datos_listos' && !saleEsPagoTarjeta(sale)" class="mt-2 text-xs text-muted-foreground">
            {{ salePaymentHint(sale) }}
        </p>
        <p v-else-if="canDeliver" class="mt-2 text-xs text-emerald-700 dark:text-emerald-400">
            Confirma la entrega para enviar el mensaje de gracias y reactivar el bot para la próxima venta.
        </p>
        <p v-else-if="canShip" class="mt-2 text-xs text-sky-700 dark:text-sky-400">
            Marca enviado cuando el pedido salga hacia la clienta.
        </p>
    </div>
</template>
