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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { apiJson, getCsrfToken } from '@/composables/useApi';
import { useCurrency } from '@/composables/useCurrency';
import {
    SALE_TRANSITION_ENDPOINTS,
    type Sale,
    type SaleTransition,
} from '@/types/sale';
import { AlertTriangle, Loader2, Package } from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';

interface SaleSummary {
    nombre?: string | null;
    producto?: string | null;
    color?: string | null;
    total?: string | null;
    distrito?: string | null;
    metodo_pago?: string | null;
}

const props = defineProps<{
    open: boolean;
    sale: Sale | null;
    transition: SaleTransition | null;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    completed: [sale: Sale];
    cancelled: [];
}>();

const { format: formatMoney } = useCurrency();

const message = ref('');
const saleSummary = ref<SaleSummary | null>(null);
const templateInvalid = ref(false);
const receiptPhoto = ref<File | null>(null);
const receiptPreviewUrl = ref<string | null>(null);
const loading = ref(false);
const submitting = ref(false);
const closedBySubmit = ref(false);
const suppressCancelEmit = ref(false);
const error = ref<string | null>(null);

const esConfirmacionPago = computed(() => props.transition === 'confirm_payment');

const isShalomDelivery = computed(() => {
    const type = props.sale?.delivery_type?.toLowerCase() ?? '';
    return type.includes('shalom') || type.includes('shalon');
});

const modalCopy = computed(() => {
    switch (props.transition) {
        case 'confirm_payment':
            return {
                title: 'Confirmar pago',
                description: 'Revisa el pedido y el mensaje. Al confirmar se envía por WhatsApp y baja stock.',
                submit: 'Confirmar y enviar',
            };
        case 'mark_shipped':
            return {
                title: 'Marcar enviado',
                description: 'Avisa a la clienta que el pedido salió. Opcionalmente adjunta la boleta o guía (Shalom).',
                submit: 'Enviar y marcar enviado',
            };
        case 'mark_delivered':
            return {
                title: 'Marcar entregado',
                description: 'Mensaje de gracias por WhatsApp. Al confirmar se reactivará el bot.',
                submit: 'Enviar gracias y activar bot',
            };
        default:
            return {
                title: 'Mensaje al cliente',
                description: 'Revisa el mensaje que se enviará por WhatsApp.',
                submit: 'Enviar y continuar',
            };
    }
});

const loadPreview = async () => {
    if (!props.sale || !props.transition) {
        return;
    }

    loading.value = true;
    error.value = null;

    try {
        const data = await apiJson<{
            message: string;
            sale_summary?: SaleSummary;
            template_invalid?: boolean;
        }>(
            `/api/sales/${props.sale.id}/transition-preview?transition=${encodeURIComponent(props.transition)}`,
        );
        message.value = data.message;
        saleSummary.value = data.sale_summary ?? null;
        templateInvalid.value = Boolean(data.template_invalid);
    } catch (err) {
        error.value = err instanceof Error ? err.message : 'No se pudo cargar el mensaje';
        message.value = '';
        saleSummary.value = null;
    } finally {
        loading.value = false;
    }
};

watch(
    () => [props.open, props.sale?.id, props.transition] as const,
    ([open]) => {
        if (open) {
            closedBySubmit.value = false;
            suppressCancelEmit.value = true;
            receiptPhoto.value = null;
            receiptPreviewUrl.value = null;
            void loadPreview();
            nextTick(() => {
                suppressCancelEmit.value = false;
            });
        }
    },
);

const onReceiptSelected = (event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;
    receiptPhoto.value = file;

    if (receiptPreviewUrl.value) {
        URL.revokeObjectURL(receiptPreviewUrl.value);
    }

    receiptPreviewUrl.value = file ? URL.createObjectURL(file) : null;
};

const onOpenChange = (value: boolean) => {
    if (!value && props.open && !closedBySubmit.value && !suppressCancelEmit.value) {
        emit('cancelled');
    }

    if (value) {
        closedBySubmit.value = false;
    }

    emit('update:open', value);
};

const submit = async () => {
    if (!props.sale || !props.transition || !message.value.trim()) {
        return;
    }

    submitting.value = true;
    error.value = null;

    try {
        const endpoint = SALE_TRANSITION_ENDPOINTS[props.transition];
        let sale: Sale;

        if (props.transition === 'mark_shipped' && receiptPhoto.value) {
            const formData = new FormData();
            formData.append('message', message.value.trim());
            formData.append('photo', receiptPhoto.value);

            sale = await apiJson<Sale>(`/api/sales/${props.sale.id}/${endpoint}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                body: formData,
            });
        } else {
            sale = await apiJson<Sale>(`/api/sales/${props.sale.id}/${endpoint}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                body: JSON.stringify({ message: message.value.trim() }),
            });
        }

        closedBySubmit.value = true;
        emit('completed', sale);
        emit('update:open', false);
    } catch (err) {
        error.value = err instanceof Error ? err.message : 'No se pudo completar la acción';
    } finally {
        submitting.value = false;
    }
};
</script>

<template>
    <Dialog :open="open" @update:open="onOpenChange">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ modalCopy.title }}</DialogTitle>
                <DialogDescription>
                    {{ modalCopy.description }}
                </DialogDescription>
            </DialogHeader>

            <div
                v-if="saleSummary && esConfirmacionPago"
                class="rounded-lg border border-emerald-200 bg-emerald-50/80 p-3 dark:border-emerald-900 dark:bg-emerald-950/30"
            >
                <div class="flex items-center gap-2 text-sm font-semibold text-emerald-900 dark:text-emerald-100">
                    <Package class="h-4 w-4" />
                    Pedido a confirmar
                </div>
                <dl class="mt-2 grid grid-cols-2 gap-x-3 gap-y-1 text-xs">
                    <template v-if="saleSummary.nombre">
                        <dt class="text-muted-foreground">Clienta</dt>
                        <dd class="font-medium">{{ saleSummary.nombre }}</dd>
                    </template>
                    <template v-if="saleSummary.producto">
                        <dt class="text-muted-foreground">Producto</dt>
                        <dd class="font-medium">{{ saleSummary.producto }}</dd>
                    </template>
                    <template v-if="saleSummary.color">
                        <dt class="text-muted-foreground">Color</dt>
                        <dd class="font-medium">{{ saleSummary.color }}</dd>
                    </template>
                    <template v-if="saleSummary.total">
                        <dt class="text-muted-foreground">Total</dt>
                        <dd class="font-semibold text-emerald-800 dark:text-emerald-300">
                            {{ formatMoney(saleSummary.total) }}
                        </dd>
                    </template>
                    <template v-if="saleSummary.distrito">
                        <dt class="text-muted-foreground">Distrito</dt>
                        <dd>{{ saleSummary.distrito }}</dd>
                    </template>
                    <template v-if="saleSummary.metodo_pago">
                        <dt class="text-muted-foreground">Pago</dt>
                        <dd>{{ saleSummary.metodo_pago }}</dd>
                    </template>
                </dl>
            </div>

            <p
                v-if="templateInvalid && esConfirmacionPago"
                class="flex items-start gap-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200"
            >
                <AlertTriangle class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                Tu plantilla en Configuración tenía formato incorrecto. Se usa la plantilla por defecto.
                Corrígela en Flujo de ventas → Confirmación de pagos.
            </p>

            <div v-if="loading" class="flex items-center gap-2 py-6 text-sm text-muted-foreground">
                <Loader2 class="h-4 w-4 animate-spin" />
                Preparando mensaje…
            </div>

            <div v-else class="space-y-2">
                <Label for="transition-message">
                    {{ esConfirmacionPago ? 'Mensaje listo para enviar' : 'Mensaje para la clienta' }}
                </Label>
                <Textarea
                    id="transition-message"
                    v-model="message"
                    rows="5"
                    class="resize-none"
                    :readonly="esConfirmacionPago"
                    :class="esConfirmacionPago ? 'bg-muted/50' : ''"
                    placeholder="Mensaje para la clienta…"
                />
                <p v-if="esConfirmacionPago" class="text-xs text-muted-foreground">
                    Generado desde Configuración con los datos del pedido. Solo confirma y envía.
                </p>
                <p v-else class="text-xs text-muted-foreground">
                    Variables: {nombre}, {producto}, {color}, {total}, {distrito}, {metodo_pago}
                </p>

                <div v-if="transition === 'mark_shipped'" class="space-y-2 rounded-lg border bg-muted/30 p-3">
                    <Label for="receipt-photo" class="text-xs font-medium">
                        Foto de boleta o guía (opcional)
                    </Label>
                    <Input
                        id="receipt-photo"
                        type="file"
                        accept="image/*"
                        class="text-xs"
                        @change="onReceiptSelected"
                    />
                    <p v-if="isShalomDelivery" class="text-[11px] text-muted-foreground">
                        Envío por Shalom: adjunta la boleta o captura de la guía.
                    </p>
                    <img
                        v-if="receiptPreviewUrl"
                        :src="receiptPreviewUrl"
                        alt="Vista previa boleta"
                        class="h-24 w-24 rounded border object-cover"
                    />
                </div>
            </div>

            <p v-if="error" class="text-sm text-destructive">{{ error }}</p>

            <DialogFooter>
                <Button
                    variant="outline"
                    :disabled="submitting"
                    @click="closedBySubmit = true; emit('cancelled'); emit('update:open', false)"
                >
                    Cancelar
                </Button>
                <Button :disabled="submitting || loading || !message.trim()" @click="submit">
                    <Loader2 v-if="submitting" class="mr-2 h-4 w-4 animate-spin" />
                    {{ modalCopy.submit }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
