<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { apiJson, getCsrfToken } from '@/composables/useApi';
import { useCurrency } from '@/composables/useCurrency';
import { usePipelineArchive } from '@/composables/useSale';
import { PIPELINE_ENTREGADOS_KANBAN_LIMIT, type Sale } from '@/types/sale';
import { Link } from '@inertiajs/vue3';
import {
    ChevronLeft,
    ChevronRight,
    ExternalLink,
    Loader2,
    RotateCcw,
    Search,
} from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = defineProps<{
    open: boolean;
    archivedCount: number;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    reverted: [];
}>();

const { sales, loading, error, page, lastPage, total, search, loadArchive } = usePipelineArchive();

const searchInput = ref('');
const revertingId = ref<number | null>(null);

const { format: formatMoney } = useCurrency();

const applySearch = () => {
    search.value = searchInput.value.trim();
    page.value = 1;
    void loadArchive();
};

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            page.value = 1;
            searchInput.value = search.value;
            void loadArchive();
        }
    },
);

watch(page, () => {
    if (props.open) {
        void loadArchive();
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

const displayName = (sale: Sale): string =>
    sale.customer_name ?? sale.customer?.name ?? sale.phone_number;

const revertToEnviado = async (sale: Sale) => {
    if (!window.confirm('¿Reabrir este pedido? Volverá a Enviado y el bot se pausará de nuevo.')) {
        return;
    }

    revertingId.value = sale.id;

    try {
        await apiJson(`/api/sales/${sale.id}/revert-delivered`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': getCsrfToken(),
            },
        });

        emit('reverted');
        void loadArchive();
    } catch (err) {
        window.alert(err instanceof Error ? err.message : 'No se pudo revertir el pedido');
    } finally {
        revertingId.value = null;
    }
};
</script>

<template>
    <Sheet :open="open" @update:open="emit('update:open', $event)">
        <SheetContent side="right" class="flex w-full flex-col sm:max-w-2xl">
            <SheetHeader class="text-left">
                <SheetTitle>Entregados anteriores</SheetTitle>
                <SheetDescription>
                    Pedidos fuera del kanban (más allá de los {{ PIPELINE_ENTREGADOS_KANBAN_LIMIT }} más recientes).
                    Puedes revertir a Enviado si hubo un error.
                </SheetDescription>
            </SheetHeader>

            <div class="mt-6 flex flex-wrap items-center gap-3">
                <div class="relative min-w-[200px] flex-1">
                    <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        v-model="searchInput"
                        class="pl-9"
                        placeholder="Buscar teléfono, producto o distrito…"
                        @keydown.enter="applySearch"
                    />
                </div>
                <Button variant="secondary" size="sm" :disabled="loading" @click="applySearch">
                    Buscar
                </Button>
            </div>

            <p v-if="archivedCount > 0 && !loading" class="mt-3 text-sm text-muted-foreground">
                {{ total }} en archivo · {{ archivedCount }} fuera del kanban
            </p>

            <p v-if="error" class="mt-3 text-sm text-destructive">{{ error }}</p>

            <div class="mt-4 min-h-0 flex-1 overflow-y-auto">
                <div v-if="loading && sales.length === 0" class="flex justify-center py-16">
                    <Loader2 class="h-6 w-6 animate-spin text-muted-foreground" />
                </div>

                <div
                    v-else-if="sales.length === 0"
                    class="rounded-xl border border-dashed py-16 text-center"
                >
                    <p class="text-sm text-muted-foreground">
                        {{ archivedCount === 0
                            ? 'Todos los entregados están visibles en el kanban.'
                            : 'No hay coincidencias en el archivo.' }}
                    </p>
                </div>

                <div v-else class="overflow-hidden rounded-xl border bg-card shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[640px] text-sm">
                            <thead class="sticky top-0 border-b bg-muted/80 text-left text-xs uppercase tracking-wide text-muted-foreground backdrop-blur-sm">
                                <tr>
                                    <th class="px-4 py-3 font-medium">Pedido</th>
                                    <th class="px-4 py-3 font-medium">Clienta</th>
                                    <th class="px-4 py-3 font-medium">Total</th>
                                    <th class="px-4 py-3 font-medium">Entregado</th>
                                    <th class="px-4 py-3 font-medium text-right">Acciones</th>
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
                                        <p class="font-medium">{{ displayName(sale) }}</p>
                                        <p class="text-xs text-muted-foreground">
                                            {{ sale.product_name }}
                                            <span v-if="sale.color">· {{ sale.color }}</span>
                                        </p>
                                    </td>
                                    <td class="px-4 py-3 font-medium">{{ formatMoney(sale.total_amount) }}</td>
                                    <td class="px-4 py-3 text-muted-foreground">
                                        {{ formatDate(sale.delivered_at) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex justify-end gap-1">
                                            <Button as-child size="sm" variant="ghost" class="h-8 gap-1">
                                                <Link :href="`/chat?phone=${encodeURIComponent(sale.phone_number)}`">
                                                    <ExternalLink class="h-3.5 w-3.5" />
                                                    Chat
                                                </Link>
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                class="h-8 gap-1"
                                                :disabled="revertingId === sale.id"
                                                @click="revertToEnviado(sale)"
                                            >
                                                <Loader2
                                                    v-if="revertingId === sale.id"
                                                    class="h-3.5 w-3.5 animate-spin"
                                                />
                                                <RotateCcw v-else class="h-3.5 w-3.5" />
                                                Revertir
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div v-if="lastPage > 1" class="mt-4 flex shrink-0 items-center justify-between gap-3 border-t pt-4">
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
        </SheetContent>
    </Sheet>
</template>
