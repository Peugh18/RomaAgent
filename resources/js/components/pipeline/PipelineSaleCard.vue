<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useCurrency } from '@/composables/useCurrency';
import {
    saleCanConfirmPayment,
    saleCanMarkDelivered,
    saleCanMarkShipped,
    saleMarkDeliveredLabel,
    saleMarkShippedLabel,
    saleVerifyPaymentLabel,
    type Sale,
    type SaleStatus,
    type SaleTransition,
} from '@/types/sale';
import { Link } from '@inertiajs/vue3';
import { CheckCircle, ExternalLink, GripVertical, MapPin, MessageCircle, Package, Truck } from 'lucide-vue-next';

defineProps<{
    sale: Sale;
    status: SaleStatus;
    draggable?: boolean;
    borderClass?: string;
}>();

const emit = defineEmits<{
    openTransition: [sale: Sale, transition: SaleTransition];
}>();

const { format: formatMoney } = useCurrency();

const displayName = (sale: Sale) =>
    sale.customer_name ?? sale.customer?.name ?? sale.phone_number;

const displayAddress = (sale: Sale) =>
    sale.delivery_address
    ?? sale.customer_data?.direccion
    ?? sale.customer_data?.address
    ?? null;
</script>

<template>
    <Card
        :class="[
            'border shadow-none transition-colors hover:bg-muted/50',
            borderClass,
            draggable ? 'cursor-grab active:cursor-grabbing' : '',
        ]"
    >
        <CardContent class="space-y-2 p-3">
            <div class="flex items-start gap-2">
                <GripVertical
                    v-if="draggable"
                    class="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground/60"
                />
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-muted">
                    <Package class="h-4 w-4 text-muted-foreground" />
                </div>
                <div class="min-w-0 flex-1 space-y-1">
                    <div class="flex items-start justify-between gap-2">
                        <p class="truncate text-sm font-medium">
                            {{ displayName(sale) }}
                        </p>
                        <span class="shrink-0 rounded bg-muted px-1.5 py-0.5 font-mono text-[10px] text-muted-foreground">
                            #{{ sale.id }}
                        </span>
                    </div>
                    <p class="truncate text-xs text-muted-foreground">
                        {{ sale.product_name }}
                        <span v-if="sale.color"> · {{ sale.color }}</span>
                        <span v-if="sale.size"> · {{ sale.size }}</span>
                    </p>
                    <p v-if="displayAddress(sale)" class="line-clamp-2 text-[11px] text-muted-foreground">
                        {{ displayAddress(sale) }}
                    </p>
                    <div v-if="sale.maps_url" class="flex items-center gap-1">
                        <a
                            :href="sale.maps_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-0.5 text-[11px] text-primary hover:underline"
                        >
                            <MapPin class="h-3 w-3" />
                            Ver GPS
                            <ExternalLink class="h-2.5 w-2.5" />
                        </a>
                    </div>
                    <p class="text-xs font-medium">
                        {{ formatMoney(sale.total_amount) }}
                        <span v-if="sale.payment_method" class="font-normal text-muted-foreground">
                            · {{ sale.payment_method }}
                        </span>
                    </p>
                    <a
                        v-if="sale.comprobante_url"
                        :href="sale.comprobante_url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-block"
                    >
                        <img
                            :src="sale.comprobante_url"
                            alt="Comprobante"
                            class="mt-1 h-14 w-14 rounded border object-cover"
                        />
                    </a>
                </div>
            </div>

            <div class="flex flex-wrap gap-1.5 pt-1">
                <Button
                    v-if="saleCanConfirmPayment(sale)"
                    size="sm"
                    variant="secondary"
                    class="h-7 gap-1 px-2 text-[11px]"
                    @click.stop="emit('openTransition', sale, 'confirm_payment')"
                >
                    <CheckCircle class="h-3 w-3" />
                    {{ saleVerifyPaymentLabel(sale) }}
                </Button>
                <Button
                    v-if="saleCanMarkShipped(sale)"
                    size="sm"
                    variant="secondary"
                    class="h-7 gap-1 px-2 text-[11px]"
                    @click.stop="emit('openTransition', sale, 'mark_shipped')"
                >
                    <Truck class="h-3 w-3" />
                    {{ saleMarkShippedLabel() }}
                </Button>
                <Button
                    v-if="saleCanMarkDelivered(sale)"
                    size="sm"
                    class="h-7 gap-1 px-2 text-[11px] bg-emerald-600 text-white hover:bg-emerald-700"
                    @click.stop="emit('openTransition', sale, 'mark_delivered')"
                >
                    <CheckCircle class="h-3 w-3" />
                    {{ saleMarkDeliveredLabel() }}
                </Button>
                <span
                    v-if="status === 'entregado'"
                    class="inline-flex h-7 items-center rounded-md bg-emerald-100 px-2 text-[10px] font-medium text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300"
                >
                    IA activa
                </span>
                <Button
                    as-child
                    variant="ghost"
                    size="sm"
                    class="h-7 gap-1 px-2 text-[11px]"
                >
                    <Link :href="`/chat?phone=${encodeURIComponent(sale.phone_number)}`" @click.stop>
                        <MessageCircle class="h-3 w-3" />
                        Chat
                    </Link>
                </Button>
            </div>
        </CardContent>
    </Card>
</template>
