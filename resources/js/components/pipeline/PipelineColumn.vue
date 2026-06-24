<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import PipelineSaleCard from '@/components/pipeline/PipelineSaleCard.vue';
import { isDraggablePipelineColumn } from '@/lib/pipelineTransitions';
import { SALE_STATUS_LABELS, type Sale, type SaleStatus } from '@/types/sale';
import { useCurrency } from '@/composables/useCurrency';
import { computed } from 'vue';
import { VueDraggable } from 'vue-draggable-plus';
import type { SortableEvent } from 'sortablejs';
import { Archive, ChevronRight } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    status: SaleStatus;
    sales: Sale[];
    highlightAttention?: boolean;
    entregadosTotal?: number;
    archivedCount?: number;
}>();

const emit = defineEmits<{
    openTransition: [sale: Sale, transition: 'confirm_payment' | 'mark_shipped' | 'mark_delivered'];
    openDetail: [sale: Sale];
    columnChange: [payload: { toStatus: SaleStatus; event: SortableEvent }];
    'update:sales': [sales: Sale[]];
    openArchive: [];
}>();

const { format: formatMoney } = useCurrency();

const draggable = computed(() => isDraggablePipelineColumn(props.status));

const localSales = computed({
    get: () => props.sales,
    set: (value: Sale[]) => emit('update:sales', value),
});

const statusConfig: Record<SaleStatus, { bg: string; border: string; badge: string }> = {
    consultando: { bg: 'bg-slate-50/50 dark:bg-slate-950/20', border: 'border-slate-200 dark:border-slate-800', badge: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300' },
    cotizando: { bg: 'bg-blue-50/50 dark:bg-blue-950/20', border: 'border-blue-200 dark:border-blue-900', badge: 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300' },
    datos_listos: { bg: 'bg-violet-50/50 dark:bg-violet-950/20', border: 'border-violet-200 dark:border-violet-900', badge: 'bg-violet-100 text-violet-700 dark:bg-violet-900 dark:text-violet-300' },
    pago_pendiente: { bg: 'bg-amber-50/50 dark:bg-amber-950/20', border: 'border-amber-200 dark:border-amber-900', badge: 'bg-amber-100 text-amber-700 dark:bg-amber-900 dark:text-amber-300' },
    pago_recibido: { bg: 'bg-orange-50/50 dark:bg-orange-950/20', border: 'border-orange-200 dark:border-orange-900', badge: 'bg-orange-100 text-orange-700 dark:bg-orange-900 dark:text-orange-300' },
    confirmado: { bg: 'bg-emerald-50/50 dark:bg-emerald-950/20', border: 'border-emerald-200 dark:border-emerald-900', badge: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300' },
    enviado: { bg: 'bg-sky-50/50 dark:bg-sky-950/20', border: 'border-sky-200 dark:border-sky-900', badge: 'bg-sky-100 text-sky-700 dark:bg-sky-900 dark:text-sky-300' },
    entregado: { bg: 'bg-emerald-50/50 dark:bg-emerald-950/20', border: 'border-emerald-200 dark:border-emerald-900', badge: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300' },
    cancelado: { bg: 'bg-rose-50/50 dark:bg-rose-950/20', border: 'border-rose-200 dark:border-rose-900', badge: 'bg-rose-100 text-rose-700 dark:bg-rose-900 dark:text-rose-300' },
};

const columnTotal = computed(() =>
    props.sales.reduce((sum, s) => sum + Number(s.total_amount), 0),
);

const badgeCount = computed(() => {
    if (props.status === 'entregado' && props.entregadosTotal !== undefined) {
        return props.entregadosTotal;
    }

    return props.sales.length;
});

const showArchiveLink = computed(
    () => props.status === 'entregado' && (props.archivedCount ?? 0) > 0,
);

const dragGroup = computed(() =>
    draggable.value
        ? { name: 'pipeline-logistics', pull: true, put: true }
        : undefined,
);

const onColumnAdd = (event: SortableEvent) => {
    emit('columnChange', { toStatus: props.status, event });
};
</script>

<template>
    <Card
        :data-pipeline-status="status"
        :class="[
            'min-w-[220px] flex-1 shrink-0 snap-center rounded-2xl border border-border/40 shadow-sm transition-shadow hover:shadow-md flex flex-col',
            statusConfig[status].bg,
            highlightAttention && sales.length > 0 ? 'ring-2 ring-amber-500/50 ring-offset-2 ring-offset-background' : '',
        ]"
    >
        <CardHeader class="space-y-3 pb-3">
            <div class="text-center w-full">
                <p v-if="status === 'confirmado'" class="text-xs font-medium text-muted-foreground uppercase tracking-wider">
                    Pedido por Preparar
                </p>
                <p v-else-if="status === 'enviado'" class="text-xs font-medium text-muted-foreground uppercase tracking-wider">
                    Pedido Preparado
                </p>
                <p v-else-if="status === 'entregado'" class="text-xs font-medium text-muted-foreground uppercase tracking-wider">
                    Pedido Entregado
                </p>
            </div>
            <div class="flex items-center justify-between mt-1">
                <Badge variant="secondary" :class="['font-medium', statusConfig[status].badge]">
                    {{ badgeCount }}
                </Badge>
                <span v-if="columnTotal > 0" class="text-xs font-medium text-muted-foreground">
                    {{ formatMoney(columnTotal) }}
                </span>
            </div>
            <CardTitle class="text-sm font-semibold">
                {{ SALE_STATUS_LABELS[status] }}
            </CardTitle>
            <p v-if="status === 'confirmado'" class="text-[10px] leading-snug text-muted-foreground">
                Arrastra o usa el botón · al soltar se abre el modal de mensaje.
            </p>
            <p v-else-if="status === 'enviado'" class="text-[10px] leading-snug text-muted-foreground">
                Arrastra a Entregado para enviar gracias y reactivar el bot.
            </p>
            <p v-else-if="status === 'entregado'" class="text-[10px] leading-snug text-muted-foreground">
                Últimos en kanban · arrastra a Enviado para corregir recientes.
            </p>
        </CardHeader>
        <CardContent class="flex-1 overflow-y-auto pt-0 min-h-0">
            <!-- Columnas de pago: lista estática (sin drag) -->
            <div v-if="!draggable" class="space-y-3">
                <PipelineSaleCard
                    v-for="sale in sales"
                    :key="sale.id"
                    :sale="sale"
                    :status="status"
                    :border-class="statusConfig[status].border"
                    @open-detail="(s: Sale) => emit('openDetail', s)"
                />
            </div>

            <!-- Logística: drag entre Confirmado / Enviado / Entregado -->
            <VueDraggable
                v-else
                v-model="localSales"
                :group="dragGroup"
                item-key="id"
                class="min-h-[5rem] space-y-3"
                :animation="180"
                ghost-class="opacity-40"
                @add="onColumnAdd"
            >
                <div
                    v-for="sale in localSales"
                    :key="sale.id"
                    :data-sale-id="sale.id"
                    class="cursor-grab active:cursor-grabbing"
                >
                    <PipelineSaleCard
                        :sale="sale"
                        :status="status"
                        draggable
                        :border-class="statusConfig[status].border"
                        @open-detail="(s: Sale) => emit('openDetail', s)"
                    />
                </div>
            </VueDraggable>

            <div
                v-if="sales.length === 0"
                class="rounded-lg border border-dashed py-8 text-center"
                :class="statusConfig[status].border"
            >
                <p class="text-xs text-muted-foreground">
                    {{ draggable ? 'Sin pedidos · suelta aquí' : 'Sin pedidos' }}
                </p>
            </div>

            <Button
                v-if="showArchiveLink"
                variant="secondary"
                size="sm"
                class="mt-3 w-full gap-2 text-xs"
                @click="emit('openArchive')"
            >
                <Archive class="h-3.5 w-3.5" />
                Ver {{ archivedCount }} entregados anteriores
                <ChevronRight class="ml-auto h-3.5 w-3.5" />
            </Button>
        </CardContent>
    </Card>
</template>
