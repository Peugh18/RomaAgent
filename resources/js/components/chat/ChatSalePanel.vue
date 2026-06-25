<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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

const linkModalOpen = ref(false);
const linkInputValue = ref('');
const sendingLink = ref(false);
const linkError = ref<string | null>(null);
const inputCustomLink = ref('');

const esTarjetaPendiente = computed(
    () => props.sale !== null && saleEsPagoTarjeta(props.sale) && props.sale.status === 'pago_pendiente',
);

const abrirModalLink = () => {
    linkInputValue.value = '';
    linkError.value = null;
    linkModalOpen.value = true;
};

const enviarLinkPago = async () => {
    if (!props.sale || sendingLink.value || !inputCustomLink.value.trim()) {
        return;
    }

    const link = inputCustomLink.value.trim();
    if (!link) {
        linkError.value = 'El link no puede estar vacío';
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
            body: JSON.stringify({
                link: inputCustomLink.value.trim(),
            }),
        });
        inputCustomLink.value = '';
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
    ?? props.sale?.customer_data?.nombre_completo
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

const tipoEnvio = computed(() => props.sale?.customer_data?.tipo_envio ?? null);
const costoReferencial = computed(() => props.sale?.customer_data?.costo_referencial ?? null);
const deliveryDistrito = computed(() => props.sale?.customer_data?.distrito ?? null);
const deliverySedeShalom = computed(() => props.sale?.customer_data?.sede_shalom ?? null);
const deliveryDni = computed(() => props.sale?.customer_data?.dni ?? null);
const deliveryProvincia = computed(() => props.sale?.customer_data?.provincia ?? null);

const hasDispatchData = computed(() => 
    tipoEnvio.value || deliveryDistrito.value || deliveryAddress.value || 
    deliveryProvincia.value || deliverySedeShalom.value || mapsUrl.value
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
        class="border-b border-border bg-card px-3 py-2.5"
    >
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0 space-y-1">
                <template v-if="sale.items && sale.items.length > 0">
                    <div v-for="item in sale.items" :key="item.id" class="flex items-center gap-2 text-sm font-semibold text-foreground">
                        <Package class="h-4 w-4 shrink-0 text-primary" />
                        <span class="truncate">{{ item.quantity }}x {{ item.product_name }}</span>
                        <span v-if="item.size" class="font-normal text-muted-foreground">· {{ item.size }}</span>
                        <span v-if="item.color" class="font-normal text-muted-foreground">· {{ item.color }}</span>
                    </div>
                </template>
                <div v-else class="flex items-center gap-2 text-sm font-semibold text-foreground">
                    <Package class="h-4 w-4 shrink-0 text-primary" />
                    <span class="truncate">{{ sale.quantity }}x {{ sale.product_name }}</span>
                    <span v-if="sale.size" class="font-normal text-muted-foreground">· {{ sale.size }}</span>
                    <span v-if="sale.color" class="font-normal text-muted-foreground">· {{ sale.color }}</span>
                </div>

                <p class="text-xs text-muted-foreground">
                    <span v-if="customerName">{{ customerName }} · </span>
                    {{ statusLabel }}
                    <span v-if="sale.payment_method"> · {{ sale.payment_method }}</span>
                </p>
                <p class="text-sm font-medium text-foreground">
                    Total: {{ formatMoney(sale.total_amount) }}
                    <span v-if="sale.items && sale.items.length > 0" class="text-xs font-normal text-muted-foreground">
                        ({{ sale.items.length }} artículos)
                    </span>
                    <span v-else class="text-xs font-normal text-muted-foreground">
                        ({{ formatMoney(sale.unit_price) }} x {{ sale.quantity }})
                    </span>
                </p>
                
                <!-- Dispatch Info Block -->
                <div v-if="hasDispatchData" class="mt-3 rounded-md border border-border/60 bg-muted/20 p-2.5 space-y-1.5">
                    <h5 class="text-[11px] font-semibold text-foreground flex items-center gap-1.5 mb-1.5 border-b pb-1">
                        📦 Información de Despacho
                    </h5>
                    
                    <template v-if="tipoEnvio === 'motorizado' || (!tipoEnvio && deliveryAddress)">
                        <p class="text-xs flex items-center gap-1.5 font-medium">
                            <span class="text-lg leading-none">🛵</span> 
                            Motorizado
                            <span v-if="costoReferencial" class="text-muted-foreground font-normal ml-1">
                                (Costo ref: {{ formatMoney(costoReferencial) }})
                            </span>
                            <span v-else-if="!tipoEnvio" class="text-amber-600 font-normal ml-1">(Inferido)</span>
                        </p>
                        <p v-if="deliveryDistrito" class="text-xs text-muted-foreground flex justify-between gap-2">
                            <strong class="text-foreground font-medium shrink-0">Distrito:</strong> <span class="truncate">{{ deliveryDistrito }}</span>
                        </p>
                        <p v-if="deliveryAddress" class="text-xs text-muted-foreground flex justify-between gap-2">
                            <strong class="text-foreground font-medium shrink-0">Dirección:</strong> <span class="truncate">{{ deliveryAddress }}</span>
                        </p>
                        <p v-if="customerName" class="text-xs text-muted-foreground flex justify-between gap-2">
                            <strong class="text-foreground font-medium shrink-0">Recibe:</strong> <span class="truncate">{{ customerName }}</span>
                        </p>
                    </template>
                    
                    <template v-else-if="tipoEnvio === 'shalom' || (!tipoEnvio && (deliveryProvincia || deliverySedeShalom))">
                        <p class="text-xs flex items-center gap-1.5 font-medium">
                            <span class="text-lg leading-none">🚚</span> 
                            Shalom 
                            <span v-if="tipoEnvio" class="text-muted-foreground font-normal ml-1">(Pago en destino)</span>
                            <span v-else class="text-amber-600 font-normal ml-1">(Inferido)</span>
                        </p>
                        <p v-if="deliveryDistrito" class="text-xs text-muted-foreground flex justify-between gap-2">
                            <strong class="text-foreground font-medium shrink-0">Destino:</strong> <span class="truncate">{{ deliveryDistrito }} <span v-if="deliveryProvincia">- {{ deliveryProvincia }}</span></span>
                        </p>
                        <p v-if="deliverySedeShalom" class="text-xs text-muted-foreground flex justify-between gap-2">
                            <strong class="text-foreground font-medium shrink-0">Agencia:</strong> <span class="truncate">{{ deliverySedeShalom }}</span>
                        </p>
                        <p v-if="customerName" class="text-xs text-muted-foreground flex justify-between gap-2">
                            <strong class="text-foreground font-medium shrink-0">Recoge:</strong> <span class="truncate">{{ customerName }} <span v-if="deliveryDni">- DNI: {{ deliveryDni }}</span></span>
                        </p>
                    </template>

                    <div v-if="mapsUrl" class="ml-6 mt-1.5">
                        <a
                            :href="mapsUrl"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-1 rounded bg-secondary px-2 py-1 text-[11px] font-medium text-secondary-foreground hover:bg-secondary/80 transition-colors"
                        >
                            <MapPin class="h-3 w-3" />
                            Abrir Ubicación
                        </a>
                    </div>
                </div>
            </div>

            <div class="flex shrink-0 flex-wrap gap-2">

                <div v-if="esTarjetaPendiente" class="flex flex-col gap-1.5 border rounded-lg p-2 bg-indigo-50/50 dark:bg-indigo-950/20 max-w-sm mr-2">
                    <Label class="text-[11px] font-semibold text-indigo-950 dark:text-indigo-200">
                        Link de Pago Tarjeta (Manual)
                    </Label>
                    <div class="flex gap-1.5">
                        <Input
                            v-model="inputCustomLink"
                            class="h-8 text-xs w-48 bg-background"
                            placeholder="Pegar link generado..."
                            :disabled="sendingLink"
                        />
                        <Button
                            size="sm"
                            variant="secondary"
                            class="h-8 gap-1 text-xs shrink-0"
                            :disabled="sendingLink || !inputCustomLink.trim()"
                            @click="enviarLinkPago"
                        >
                            <Loader2 v-if="sendingLink" class="h-3.5 w-3.5 animate-spin" />
                            <ExternalLink v-else class="h-3.5 w-3.5" />
                            {{ sendingLink ? 'Enviando…' : 'Enviar' }}
                        </Button>
                    </div>
                </div>

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

        <p v-if="esTarjetaPendiente" class="mt-2 text-xs text-indigo-700 dark:text-indigo-300">
            Haz clic en "Enviar link de pago", pega el código y el bot se reactivará automáticamente para validar el voucher.
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

    <Dialog v-model:open="linkModalOpen">
        <DialogContent class="sm:max-w-[425px]">
            <DialogHeader>
                <DialogTitle>Enviar Link de Pago</DialogTitle>
                <DialogDescription>
                    Ingresa el enlace o código de pago generado para el cliente.
                </DialogDescription>
            </DialogHeader>
            <div class="grid gap-4 py-4">
                <div class="grid gap-2">
                    <Label for="link_pago" class="sr-only">Enlace de Pago</Label>
                    <Input
                        id="link_pago"
                        v-model="linkInputValue"
                        placeholder="ej. https://link.niubiz.com/..."
                        @keyup.enter="enviarLinkPago"
                        autocomplete="off"
                    />
                    <p v-if="linkError" class="text-xs text-red-500">{{ linkError }}</p>
                </div>
            </div>
            <DialogFooter>
                <Button type="button" variant="outline" @click="linkModalOpen = false" :disabled="sendingLink">
                    Cancelar
                </Button>
                <Button type="button" @click="enviarLinkPago" :disabled="sendingLink || !linkInputValue.trim()">
                    <Loader2 v-if="sendingLink" class="mr-2 h-4 w-4 animate-spin" />
                    Enviar y Reactivar IA
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
