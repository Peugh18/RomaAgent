<script setup lang="ts">
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Package, Clock, CalendarDays } from 'lucide-vue-next';
import { useCurrency } from '@/composables/useCurrency';
import { SALE_STATUS_LABELS } from '@/types/sale';

const open = defineModel<boolean>('open', { default: false });

const props = defineProps<{
    customer: any | null;
}>();

const { format: formatMoney } = useCurrency();

const formatDate = (dateString: string) => {
    if (!dateString) return 'Desconocida';
    return new Date(dateString).toLocaleDateString('es-PE', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};
</script>

<template>
    <Sheet v-model:open="open">
        <SheetContent side="right" class="w-full max-w-md p-0 sm:max-w-md bg-[#f9fafb] dark:bg-card">
            <SheetHeader class="border-b border-border bg-muted/30 px-4 py-4 text-left">
                <SheetTitle class="text-base flex items-center gap-2">
                    <Clock class="h-5 w-5 text-emerald-600" />
                    Historial de Compras
                </SheetTitle>
                <p v-if="customer" class="text-xs text-muted-foreground mt-1">
                    {{ customer.name || customer.phone_number }}
                </p>
            </SheetHeader>
            
            <div class="h-full overflow-y-auto p-4 pb-20 space-y-4">
                <div v-if="!customer" class="text-sm text-center text-muted-foreground py-8">
                    Cargando historial...
                </div>
                
                <div v-else-if="!customer.sales || customer.sales.length === 0" class="flex flex-col items-center justify-center text-center py-16 px-4">
                    <div class="h-16 w-16 rounded-full bg-muted flex items-center justify-center mb-4">
                        <Package class="h-8 w-8 text-muted-foreground/50" />
                    </div>
                    <h3 class="text-lg font-semibold text-foreground mb-1">Sin historial de compras</h3>
                    <p class="text-sm text-muted-foreground">
                        Este cliente aún no ha registrado ninguna compra en el sistema.
                    </p>
                </div>

                <div v-else v-for="sale in customer.sales" :key="sale.id" class="rounded-xl border border-border bg-card p-4 shadow-sm relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-1 h-full bg-emerald-500" v-if="sale.status === 'entregado' || sale.status === 'enviado'"></div>
                    <div class="absolute top-0 right-0 w-1 h-full bg-amber-500" v-else-if="sale.status === 'pago_pendiente' || sale.status === 'pago_recibido'"></div>
                    
                    <div class="flex justify-between items-start mb-2">
                        <span class="inline-flex items-center gap-1.5 rounded-md bg-secondary/50 px-2 py-1 text-xs font-medium text-secondary-foreground">
                            {{ SALE_STATUS_LABELS[sale.status as keyof typeof SALE_STATUS_LABELS] || sale.status }}
                        </span>
                        <span class="text-sm font-bold text-emerald-700 dark:text-emerald-400">
                            {{ formatMoney(sale.total_amount) }}
                        </span>
                    </div>

                    <div class="flex items-center gap-2 text-sm font-medium mb-2">
                        <Package class="h-4 w-4 text-muted-foreground" />
                        {{ sale.quantity }}x {{ sale.product_name }}
                    </div>

                    <div class="space-y-1 text-xs text-muted-foreground">
                        <div v-if="sale.color || sale.size">
                            <span v-if="sale.color">Color: {{ sale.color }}</span>
                            <span v-if="sale.color && sale.size"> · </span>
                            <span v-if="sale.size">Talla: {{ sale.size }}</span>
                        </div>
                        <div class="flex items-center gap-1.5 pt-1 mt-2 border-t border-border/50">
                            <CalendarDays class="h-3 w-3" />
                            Creado: {{ formatDate(sale.created_at) }}
                        </div>
                    </div>
                </div>
            </div>
        </SheetContent>
    </Sheet>
</template>
