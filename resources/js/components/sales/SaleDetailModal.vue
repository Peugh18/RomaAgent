<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useCurrency } from '@/composables/useCurrency';
import {
    saleCanConfirmPayment,
    saleCanMarkDelivered,
    saleCanMarkShipped,
    saleVerifyPaymentLabel,
    SALE_STATUS_LABELS,
    type Sale,
    type SaleTransition,
} from '@/types/sale';
import { Link } from '@inertiajs/vue3';
import {
    CheckCircle,
    MapPin,
    MessageCircle,
    Package,
    Phone,
    Truck,
    X,
} from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    open: boolean;
    sale: Sale | null;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    transition: [sale: Sale, transition: SaleTransition];
    cancel: [sale: Sale];
}>();

const { format: formatMoney } = useCurrency();

const displayName = computed(() =>
    props.sale?.customer_name ?? props.sale?.customer?.name ?? props.sale?.phone_number ?? '',
);

const displayAddress = computed(() =>
    props.sale?.delivery_address
    ?? props.sale?.customer_data?.direccion
    ?? props.sale?.customer_data?.address
    ?? null,
);

const actionButton = computed(() => {
    if (!props.sale) return null;

    if (saleCanConfirmPayment(props.sale)) {
        return { transition: 'confirm_payment' as SaleTransition, label: saleVerifyPaymentLabel(props.sale), icon: CheckCircle };
    }
    if (saleCanMarkShipped(props.sale)) {
        return { transition: 'mark_shipped' as SaleTransition, label: 'Marcar enviado', icon: Truck };
    }
    if (saleCanMarkDelivered(props.sale)) {
        return { transition: 'mark_delivered' as SaleTransition, label: 'Marcar entregado', icon: CheckCircle };
    }

    return null;
});

const onTransition = () => {
    if (!props.sale || !actionButton.value) return;
    emit('transition', props.sale, actionButton.value.transition);
    emit('update:open', false);
};

const onCancel = () => {
    if (!props.sale) return;
    emit('cancel', props.sale);
    emit('update:open', false);
};
</script>

<template>
    <Dialog :open="open" @update:open="$emit('update:open', $event)">
        <DialogContent class="sm:max-w-lg max-h-[90vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Package class="h-5 w-5" />
                    Pedido #{{ sale?.id }}
                </DialogTitle>
                <DialogDescription>
                    {{ sale ? SALE_STATUS_LABELS[sale.status] : '' }}
                </DialogDescription>
            </DialogHeader>

            <div v-if="sale" class="space-y-5">
                <!-- Producto -->
                <div class="rounded-lg border bg-muted/30 p-4">
                    <h4 class="mb-2 flex items-center gap-1.5 text-sm font-semibold">
                        <Package class="h-4 w-4" />
                        Producto a alistar
                    </h4>
                    <div class="space-y-3">
                        <template v-if="sale.items && sale.items.length > 0">
                            <div v-for="item in sale.items" :key="item.id" class="border-b border-border/50 pb-3 last:border-0 last:pb-0">
                                <dl class="grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
                                    <dt class="text-muted-foreground">Producto</dt>
                                    <dd class="font-medium">{{ item.product_name }}</dd>
                                    <dt class="text-muted-foreground">Color / Talla</dt>
                                    <dd>{{ item.color ?? '—' }} / {{ item.size }}</dd>
                                    <dt class="text-muted-foreground">Cant. x Precio</dt>
                                    <dd>{{ item.quantity }} × {{ formatMoney(item.unit_price) }}</dd>
                                </dl>
                            </div>
                        </template>
                        <template v-else>
                            <dl class="grid grid-cols-2 gap-x-4 gap-y-1 text-sm border-b border-border/50 pb-3">
                                <dt class="text-muted-foreground">Producto</dt>
                                <dd class="font-medium">{{ sale.product_name }}</dd>
                                <dt class="text-muted-foreground">Color</dt>
                                <dd>{{ sale.color ?? '—' }}</dd>
                                <dt class="text-muted-foreground">Talla</dt>
                                <dd>{{ sale.size }}</dd>
                                <dt class="text-muted-foreground">Cantidad</dt>
                                <dd>{{ sale.quantity }}</dd>
                                <dt class="text-muted-foreground">Precio unit.</dt>
                                <dd>{{ formatMoney(sale.unit_price) }}</dd>
                            </dl>
                        </template>
                    </div>
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm mt-3 pt-3">
                        <dt class="text-muted-foreground">Delivery</dt>
                        <dd>{{ formatMoney(sale.delivery_cost) }}</dd>
                        <dt class="text-muted-foreground font-semibold">Total</dt>
                        <dd class="font-semibold text-emerald-700 dark:text-emerald-300">
                            {{ formatMoney(sale.total_amount) }}
                        </dd>
                    </dl>
                </div>

                <!-- Cliente -->
                <div class="rounded-lg border bg-muted/30 p-4">
                    <h4 class="mb-2 flex items-center gap-1.5 text-sm font-semibold">
                        <Phone class="h-4 w-4" />
                        Cliente y entrega
                    </h4>
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                        <dt class="text-muted-foreground">Nombre</dt>
                        <dd class="font-medium">{{ displayName }}</dd>
                        <dt class="text-muted-foreground">Teléfono</dt>
                        <dd>{{ sale.phone_number }}</dd>
                        <dt class="text-muted-foreground">Dirección</dt>
                        <dd class="line-clamp-3">{{ displayAddress ?? '—' }}</dd>
                        <dt class="text-muted-foreground">Distrito</dt>
                        <dd>{{ sale.delivery_district ?? '—' }}</dd>
                        <dt class="text-muted-foreground">Tipo de entrega</dt>
                        <dd>{{ sale.delivery_type ?? '—' }}</dd>
                        <dt class="text-muted-foreground">Método de pago</dt>
                        <dd>{{ sale.payment_method ?? '—' }}</dd>
                        
                        <!-- Extra data from customer_data -->
                        <template v-if="sale.customer_data && Object.keys(sale.customer_data).length > 0">
                            <template v-for="([k, v]) in Object.entries(sale.customer_data).filter(([k, v]) => !['nombre', 'name', 'direccion', 'address', 'maps_url', 'ubicacion_actual', 'latitude', 'longitude'].includes(k) && v)" :key="k">
                                <dt class="text-muted-foreground capitalize">{{ k.replace(/_/g, ' ') }}</dt>
                                <dd class="break-words">{{ v }}</dd>
                            </template>
                        </template>
                    </dl>
                    <div v-if="sale.maps_url" class="mt-2">
                        <a
                            :href="sale.maps_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-1 text-xs text-primary hover:underline"
                        >
                            <MapPin class="h-3 w-3" />
                            Ver ubicación en Google Maps
                        </a>
                    </div>
                </div>

                <!-- Comprobante -->
                <div v-if="sale.comprobante_url" class="rounded-lg border bg-muted/30 p-4">
                    <h4 class="mb-2 text-sm font-semibold">Comprobante de pago</h4>
                    <a :href="sale.comprobante_url" target="_blank" rel="noopener noreferrer">
                        <img
                            :src="sale.comprobante_url"
                            alt="Comprobante"
                            class="max-h-64 w-full rounded border object-contain"
                        />
                    </a>
                </div>

                <!-- Notas -->
                <div v-if="sale.notes" class="rounded-lg border bg-amber-50/50 p-3 dark:bg-amber-950/20">
                    <p class="text-xs text-amber-800 dark:text-amber-200">{{ sale.notes }}</p>
                </div>
            </div>

            <DialogFooter class="flex-col gap-2 sm:flex-row">
                <Button
                    v-if="sale?.can_cancel"
                    variant="outline"
                    class="gap-2 text-rose-600 hover:bg-rose-50 hover:text-rose-700"
                    @click="onCancel"
                >
                    <X class="h-4 w-4" />
                    Cancelar pedido
                </Button>

                <Button
                    as-child
                    variant="outline"
                    class="gap-2"
                >
                    <Link :href="`/chat?phone=${encodeURIComponent(sale?.phone_number ?? '')}`">
                        <MessageCircle class="h-4 w-4" />
                        Ir al chat
                    </Link>
                </Button>

                <Button
                    v-if="actionButton"
                    class="gap-2"
                    @click="onTransition"
                >
                    <component :is="actionButton.icon" class="h-4 w-4" />
                    {{ actionButton.label }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
