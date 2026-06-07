<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useCurrency } from '@/composables/useCurrency';
import { usePipelineHistory } from '@/composables/useSale';
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, ExternalLink, Loader2, Search } from 'lucide-vue-next';
import { ref, watch, onMounted } from 'vue';

const { sales, loading, error, page, lastPage, total, search, loadHistory } = usePipelineHistory();

const searchInput = ref('');

const { format: formatMoney } = useCurrency();

const applySearch = () => {
    search.value = searchInput.value.trim();
    page.value = 1;
    void loadHistory();
};

watch(page, () => {
    void loadHistory();
});

onMounted(() => {
    if (sales.value.length === 0 && !loading.value) {
        void loadHistory();
    }
});

const formatDate = (value: string | null | undefined): string => {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString('es-PE', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
};
</script>

<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center gap-3">
            <div class="relative min-w-[220px] flex-1">
                <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    v-model="searchInput"
                    class="pl-9"
                    placeholder="Buscar por teléfono, producto o distrito…"
                    @keydown.enter="applySearch"
                />
            </div>
            <Button variant="secondary" :disabled="loading" @click="applySearch">
                Buscar
            </Button>
            <p v-if="total > 0" class="text-sm text-muted-foreground">
                {{ total }} entregados en total
            </p>
        </div>

        <p v-if="error" class="text-sm text-destructive">{{ error }}</p>

        <div v-if="loading && sales.length === 0" class="flex justify-center py-12">
            <Loader2 class="h-6 w-6 animate-spin text-muted-foreground" />
        </div>

        <div v-else-if="sales.length === 0" class="rounded-xl border border-dashed py-12 text-center">
            <p class="text-sm text-muted-foreground">No hay pedidos entregados en esta búsqueda.</p>
        </div>

        <div v-else class="overflow-hidden rounded-xl border bg-card shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px] text-sm">
                    <thead class="border-b bg-muted/40 text-left text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 font-medium">Pedido</th>
                            <th class="px-4 py-3 font-medium">Clienta</th>
                            <th class="px-4 py-3 font-medium">Producto</th>
                            <th class="px-4 py-3 font-medium">Total</th>
                            <th class="px-4 py-3 font-medium">Entregado</th>
                            <th class="px-4 py-3 font-medium" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="sale in sales"
                            :key="sale.id"
                            class="border-b border-border/60 last:border-0 hover:bg-muted/30"
                        >
                            <td class="px-4 py-3 font-mono text-xs">#{{ sale.id }}</td>
                            <td class="px-4 py-3">
                                <p class="font-medium">{{ sale.customer_name || sale.phone_number }}</p>
                                <p class="text-xs text-muted-foreground">{{ sale.phone_number }}</p>
                            </td>
                            <td class="px-4 py-3">
                                {{ sale.product_name }}
                                <span v-if="sale.color" class="text-muted-foreground">· {{ sale.color }}</span>
                            </td>
                            <td class="px-4 py-3 font-medium">{{ formatMoney(sale.total_amount) }}</td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ formatDate(sale.delivered_at) }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Button as-child size="sm" variant="ghost" class="gap-1">
                                    <Link :href="`/chat?phone=${encodeURIComponent(sale.phone_number)}`">
                                        <ExternalLink class="h-3.5 w-3.5" />
                                        Chat
                                    </Link>
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="lastPage > 1" class="flex items-center justify-between gap-3">
            <p class="text-xs text-muted-foreground">
                Página {{ page }} de {{ lastPage }}
            </p>
            <div class="flex gap-2">
                <Button variant="outline" size="sm" :disabled="loading || page <= 1" @click="page--">
                    <ChevronLeft class="h-4 w-4" />
                    Anterior
                </Button>
                <Button variant="outline" size="sm" :disabled="loading || page >= lastPage" @click="page++">
                    Siguiente
                    <ChevronRight class="h-4 w-4" />
                </Button>
            </div>
        </div>
    </div>
</template>
