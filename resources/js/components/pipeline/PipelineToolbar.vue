<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useCurrency } from '@/composables/useCurrency';
import { Search, Wallet, AlertCircle, Package, X } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    search: string;
    totalOrders: number;
    totalAmount: number;
    needsPaymentAttention: number;
    loading?: boolean;
}>();

const emit = defineEmits<{
    'update:search': [value: string];
    focusPayments: [];
    clearSearch: [];
}>();

const { format: formatMoney } = useCurrency();

const hasSearch = computed(() => props.search.trim().length > 0);
</script>

<template>
    <div class="space-y-4 rounded-xl border bg-card p-4 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex flex-wrap gap-3">
                <div class="flex items-center gap-2 rounded-lg bg-muted/50 px-3 py-2">
                    <Wallet class="h-4 w-4 text-muted-foreground" />
                    <div>
                        <p class="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">Valor total</p>
                        <p class="text-sm font-semibold">{{ formatMoney(totalAmount) }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-2 rounded-lg bg-muted/50 px-3 py-2">
                    <Package class="h-4 w-4 text-muted-foreground" />
                    <div>
                        <p class="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">Pedidos</p>
                        <p class="text-sm font-semibold">{{ totalOrders }}</p>
                    </div>
                </div>
                <Button
                    v-if="needsPaymentAttention > 0"
                    variant="outline"
                    size="sm"
                    class="h-auto gap-2 border-amber-500/40 bg-amber-500/10 py-2 hover:bg-amber-500/15"
                    @click="emit('focusPayments')"
                >
                    <AlertCircle class="h-4 w-4 text-amber-600 dark:text-amber-400" />
                    <span class="text-left">
                        <span class="block text-[10px] font-medium uppercase tracking-wide text-amber-700 dark:text-amber-300">
                            Requiere tu acción
                        </span>
                        <span class="text-sm font-semibold">{{ needsPaymentAttention }} en pago</span>
                    </span>
                </Button>
            </div>

            <div class="relative min-w-[240px] flex-1 lg:max-w-sm">
                <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    :model-value="search"
                    class="pl-9 pr-9"
                    placeholder="Buscar por nombre, teléfono o producto…"
                    :disabled="loading"
                    @update:model-value="emit('update:search', String($event))"
                />
                <button
                    v-if="hasSearch"
                    type="button"
                    class="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                    aria-label="Limpiar búsqueda"
                    @click="emit('clearSearch')"
                >
                    <X class="h-4 w-4" />
                </button>
            </div>
        </div>

        <div v-if="hasSearch" class="flex items-center gap-2 text-sm text-muted-foreground">
            <Badge variant="secondary">Filtro activo</Badge>
            <span>Mostrando coincidencias en todas las columnas</span>
        </div>
    </div>
</template>
