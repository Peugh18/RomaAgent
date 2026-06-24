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
import { AlertTriangle, ChevronDown, ChevronUp, Loader2, Package, Plus, Trash2 } from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';

interface SaleSummary {
    nombre?: string | null;
    producto?: string | null;
    color?: string | null;
    total?: string | null;
    metodo_pago?: string | null;
}

interface MessageBubble {
    id: string;
    content: string;
    delay_seconds: number;
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

const bubbles = ref<MessageBubble[]>([]);
const saleSummary = ref<SaleSummary | null>(null);
const templateInvalid = ref(false);
const receiptPhoto = ref<File | null>(null);
const receiptPreviewUrl = ref<string | null>(null);
const loading = ref(false);
const submitting = ref(false);
const closedBySubmit = ref(false);
const suppressCancelEmit = ref(false);
const error = ref<string | null>(null);

let bubbleCounter = 0;
const newBubbleId = () => `bubble-${++bubbleCounter}`;

const esConfirmacionPago = computed(() => props.transition === 'confirm_payment');
const esEnvio = computed(() => props.transition === 'mark_shipped');

const modalCopy = computed(() => {
    switch (props.transition) {
        case 'confirm_payment':
            return {
                title: 'Confirmar pago',
                description: 'Revisa el pedido y los mensajes. Al confirmar se envían por WhatsApp y baja stock.',
                submit: 'Confirmar y enviar',
            };
        case 'mark_shipped':
            return {
                title: 'Marcar enviado',
                description: 'Avisa a la clienta que el pedido salió. Opcionalmente adjunta la boleta.',
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
            extra_messages?: Array<{ content: string; delay_seconds: number }>;
        }>(
            `/api/sales/${props.sale.id}/transition-preview?transition=${encodeURIComponent(props.transition)}`,
        );

        // Build the initial bubble list: main message + any extra suggested by server
        bubbles.value = [
            { id: newBubbleId(), content: data.message, delay_seconds: 0 },
            ...(data.extra_messages ?? []).map((m) => ({
                id: newBubbleId(),
                content: m.content,
                delay_seconds: m.delay_seconds ?? 0,
            })),
        ];

        saleSummary.value = data.sale_summary ?? null;
        templateInvalid.value = Boolean(data.template_invalid);
    } catch (err) {
        error.value = err instanceof Error ? err.message : 'No se pudo cargar el mensaje';
        bubbles.value = [];
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

const addBubble = () => {
    bubbles.value.push({ id: newBubbleId(), content: '', delay_seconds: 0 });
};

const removeBubble = (index: number) => {
    if (bubbles.value.length <= 1) return;
    bubbles.value.splice(index, 1);
};

const moveBubble = (index: number, direction: -1 | 1) => {
    const newIndex = index + direction;
    if (newIndex < 0 || newIndex >= bubbles.value.length) return;
    const arr = [...bubbles.value];
    [arr[index], arr[newIndex]] = [arr[newIndex], arr[index]];
    bubbles.value = arr;
};

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

const canSubmit = computed(() => bubbles.value.length > 0 && bubbles.value[0].content.trim().length > 0);

const submit = async () => {
    if (!props.sale || !props.transition || !canSubmit.value) {
        return;
    }

    submitting.value = true;
    error.value = null;

    try {
        const endpoint = SALE_TRANSITION_ENDPOINTS[props.transition];
        let sale: Sale;

        const messagesPayload = bubbles.value
            .filter((b) => b.content.trim().length > 0)
            .map((b) => ({ content: b.content.trim(), delay_seconds: b.delay_seconds }));

        if (esEnvio.value && receiptPhoto.value) {
            const formData = new FormData();
            formData.append('message', messagesPayload[0]?.content ?? '');
            formData.append('photo', receiptPhoto.value);
            // Extra bubbles as JSON string
            for (let i = 1; i < messagesPayload.length; i++) {
                formData.append(`messages[${i - 1}][content]`, messagesPayload[i].content);
                formData.append(`messages[${i - 1}][delay_seconds]`, String(messagesPayload[i].delay_seconds));
            }

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
                body: JSON.stringify({ messages: messagesPayload }),
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
                Preparando mensajes…
            </div>

            <div v-else class="space-y-3">
                <!-- Message Bubbles -->
                <div
                    v-for="(bubble, index) in bubbles"
                    :key="bubble.id"
                    class="rounded-lg border bg-muted/20 p-3 space-y-2"
                >
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs font-semibold text-muted-foreground">
                            {{ index === 0 ? 'Mensaje principal' : `Mensaje ${index + 1}` }}
                            <span v-if="bubble.delay_seconds > 0" class="ml-1 text-emerald-600">
                                ({{ bubble.delay_seconds }}s de retraso)
                            </span>
                        </span>
                        <div class="flex items-center gap-1">
                            <button
                                v-if="index > 0"
                                type="button"
                                class="rounded p-0.5 hover:bg-muted"
                                title="Subir"
                                @click="moveBubble(index, -1)"
                            >
                                <ChevronUp class="h-3.5 w-3.5" />
                            </button>
                            <button
                                v-if="index < bubbles.length - 1"
                                type="button"
                                class="rounded p-0.5 hover:bg-muted"
                                title="Bajar"
                                @click="moveBubble(index, 1)"
                            >
                                <ChevronDown class="h-3.5 w-3.5" />
                            </button>
                            <button
                                v-if="bubbles.length > 1"
                                type="button"
                                class="rounded p-0.5 text-destructive hover:bg-destructive/10"
                                title="Eliminar"
                                @click="removeBubble(index)"
                            >
                                <Trash2 class="h-3.5 w-3.5" />
                            </button>
                        </div>
                    </div>

                    <Textarea
                        :id="`bubble-${bubble.id}`"
                        v-model="bubble.content"
                        rows="4"
                        class="resize-none text-sm"
                        placeholder="Mensaje para la clienta…"
                    />

                    <!-- Delay (only for extra bubbles) -->
                    <div v-if="index > 0" class="flex items-center gap-2">
                        <Label :for="`delay-${bubble.id}`" class="text-xs text-muted-foreground whitespace-nowrap">
                            Retraso (seg):
                        </Label>
                        <Input
                            :id="`delay-${bubble.id}`"
                            v-model.number="bubble.delay_seconds"
                            type="number"
                            min="0"
                            max="300"
                            class="h-7 w-20 text-xs"
                        />
                    </div>

                    <!-- Photo upload for mark_shipped (first bubble only) -->
                    <div v-if="esEnvio && index === 0" class="space-y-2 rounded-md border bg-background p-2">
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
                        <img
                            v-if="receiptPreviewUrl"
                            :src="receiptPreviewUrl"
                            alt="Vista previa boleta"
                            class="h-24 w-24 rounded border object-cover"
                        />
                    </div>
                </div>

                <!-- Add bubble button -->
                <button
                    type="button"
                    class="flex w-full items-center justify-center gap-1.5 rounded-lg border border-dashed border-border py-2 text-xs text-muted-foreground transition hover:bg-muted/50"
                    :disabled="bubbles.length >= 5"
                    @click="addBubble"
                >
                    <Plus class="h-3.5 w-3.5" />
                    Añadir otro mensaje
                </button>

                <p class="text-xs text-muted-foreground">
                    Variables: {nombre}, {producto}, {color}, {total}, {metodo_pago}
                </p>
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
                <Button :disabled="submitting || loading || !canSubmit" @click="submit">
                    <Loader2 v-if="submitting" class="mr-2 h-4 w-4 animate-spin" />
                    {{ modalCopy.submit }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
