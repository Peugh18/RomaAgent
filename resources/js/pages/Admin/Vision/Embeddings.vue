<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import CrmAlert from '@/components/crm/CrmAlert.vue';
import CrmListCard from '@/components/crm/CrmListCard.vue';
import CrmPagination from '@/components/crm/CrmPagination.vue';
import CrmSearchBar from '@/components/crm/CrmSearchBar.vue';
import PageHeader from '@/components/crm/PageHeader.vue';
import StatCard from '@/components/dashboard/StatCard.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useVisionEmbeddings } from '@/composables/useVisionEmbeddings';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import {
    CheckCircle2,
    Layers,
    Loader2,
    Package,
    RefreshCw,
    Sparkles,
    Trash2,
    XCircle,
} from 'lucide-vue-next';
import { computed, onMounted, watch } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Embeddings', href: '/admin/vision/embeddings' },
];

const {
    stats,
    products,
    loading,
    processing,
    selectedIds,
    search,
    statusFilter,
    error,
    pagination,
    toast,
    selectedCount,
    hasSelection,
    loadStats,
    loadProducts,
    processProduct,
    processSelected,
    processAll,
    processMissingImages,
    clearProduct,
    toggleSelection,
    selectAllVisible,
    clearSelection,
    refresh,
    goToPage,
    applyFilters,
    clearError,
    showToast,
    hideToast,
} = useVisionEmbeddings();

const completionVariant = computed(() => {
    const pct = stats.value.completion_percentage;
    if (pct >= 90) return 'success' as const;
    if (pct >= 50) return 'warning' as const;
    return 'danger' as const;
});

const fromItem = computed(() => {
    if (pagination.total === 0) return 0;
    return (pagination.current_page - 1) * pagination.per_page + 1;
});

const toItem = computed(() => {
    return Math.min(fromItem.value + products.value.length - 1, pagination.total);
});

const embeddingStatus = (product: { total_variants: number; variants_with_embeddings: number }) => {
    if (product.variants_with_embeddings === 0) {
        return { label: 'Sin embeddings', variant: 'destructive' as const };
    }
    if (product.variants_with_embeddings >= product.total_variants) {
        return { label: 'Completo', variant: 'default' as const };
    }
    return { label: 'Parcial', variant: 'secondary' as const };
};

const progressLabel = (product: { total_variants: number; variants_with_embeddings: number }) =>
    `${product.variants_with_embeddings} / ${product.total_variants} variantes`;

const handleProcessProduct = async (productId: number) => {
    const result = await processProduct(productId);
    if (!result) return;

    showToast(
        result.failed === 0 ? 'success' : 'warning',
        'Procesamiento terminado',
        `Éxitos: ${result.success} · Fallidos: ${result.failed}`,
    );
};

const handleProcessSelected = async () => {
    if (!hasSelection.value) {
        showToast('warning', 'Selección vacía', 'Marca al menos un producto.');
        return;
    }
    await processSelected();
};

const handleProcessAll = async () => {
    if (!confirm('¿Procesar todo el catálogo? Puede tardar varios minutos.')) return;
    await processAll();
};

const handleProcessPending = async () => {
    const result = await processMissingImages();
    if (!result) return;

    showToast(
        result.failed === 0 ? 'success' : 'warning',
        'Pendientes procesados',
        `Encontrados: ${result.found ?? 0} · Éxitos: ${result.success} · Fallidos: ${result.failed}`,
    );
};

const handleClearProduct = async (productId: number) => {
    if (!confirm('¿Eliminar embeddings de este producto?')) return;
    const ok = await clearProduct(productId);
    if (ok) showToast('success', 'Listo', 'Embeddings eliminados.');
};

watch([search, statusFilter], () => applyFilters());

onMounted(() => {
    loadStats();
    loadProducts();
});
</script>

<template>
    <Head title="Embeddings" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="crm-page">
            <PageHeader
                title="Embeddings visuales"
                description="Vectoriza el catálogo para mejorar el reconocimiento de imágenes en WhatsApp."
            >
                <template #actions>
                    <Button variant="secondary" class="gap-2" :disabled="loading || processing" @click="refresh">
                        <RefreshCw class="h-4 w-4" :class="{ 'animate-spin': loading }" />
                        Actualizar
                    </Button>
                </template>
            </PageHeader>

            <CrmAlert v-if="error" variant="error">
                <div class="flex items-center justify-between gap-3">
                    <span>{{ error }}</span>
                    <Button variant="ghost" size="sm" class="h-7 shrink-0" @click="clearError">Cerrar</Button>
                </div>
            </CrmAlert>

            <div
                v-if="toast.show"
                class="rounded-xl border px-4 py-3 text-sm shadow-sm"
                :class="{
                    'border-emerald-500/30 bg-emerald-500/10 text-emerald-800 dark:text-emerald-200': toast.type === 'success',
                    'border-destructive/30 bg-destructive/10 text-destructive': toast.type === 'error',
                    'border-amber-500/30 bg-amber-500/10 text-amber-900 dark:text-amber-100': toast.type === 'warning',
                    'border-border bg-card text-foreground': toast.type === 'info',
                }"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="font-medium">{{ toast.title }}</p>
                        <p class="mt-0.5 opacity-90">{{ toast.message }}</p>
                    </div>
                    <Button variant="ghost" size="icon" class="h-7 w-7 shrink-0" @click="hideToast">
                        <XCircle class="h-4 w-4" />
                    </Button>
                </div>
            </div>

            <div v-if="processing" class="flex items-center gap-2 rounded-xl border border-border/80 bg-muted/40 px-4 py-3 text-sm text-muted-foreground">
                <Loader2 class="h-4 w-4 animate-spin" />
                Procesando embeddings…
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard title="Productos" :value="stats.total_products" :icon="Package" :loading="loading" />
                <StatCard title="Variantes" :value="stats.total_variants" :icon="Layers" :loading="loading" />
                <StatCard
                    title="Con embeddings"
                    :value="stats.variants_with_embeddings"
                    :icon="Sparkles"
                    :loading="loading"
                    variant="success"
                />
                <StatCard
                    title="Completado"
                    :value="`${stats.completion_percentage}%`"
                    :icon="CheckCircle2"
                    :loading="loading"
                    :variant="completionVariant"
                />
            </div>

            <div class="crm-toolbar flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center">
                    <CrmSearchBar v-model="search" placeholder="Buscar producto…" :disabled="loading" class="sm:max-w-xs" />
                    <select
                        v-model="statusFilter"
                        class="h-10 rounded-md border border-input bg-background px-3 text-sm text-foreground shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    >
                        <option value="all">Todos los estados</option>
                        <option value="complete">Completos</option>
                        <option value="partial">Parciales</option>
                        <option value="none">Sin embeddings</option>
                    </select>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button variant="outline" size="sm" :disabled="loading" @click="selectAllVisible">Seleccionar todos</Button>
                    <Button variant="ghost" size="sm" :disabled="!hasSelection" @click="clearSelection">Limpiar</Button>
                    <Button size="sm" :disabled="processing || !hasSelection" @click="handleProcessSelected">
                        Procesar seleccionados ({{ selectedCount }})
                    </Button>
                    <Button variant="secondary" size="sm" :disabled="processing" @click="handleProcessAll">
                        Procesar todo
                    </Button>
                    <Button variant="outline" size="sm" :disabled="processing" @click="handleProcessPending">
                        Procesar pendientes
                    </Button>
                </div>
            </div>

            <CrmListCard>
                <div v-if="loading" class="flex items-center justify-center py-16 text-muted-foreground">
                    <Loader2 class="mr-2 h-5 w-5 animate-spin" />
                    Cargando productos…
                </div>

                <div v-else-if="products.length === 0" class="py-16 text-center text-muted-foreground">
                    No hay productos que coincidan con los filtros.
                </div>

                <template v-else>
                    <div class="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow class="hover:bg-transparent">
                                    <TableHead class="w-10" />
                                    <TableHead>Producto</TableHead>
                                    <TableHead>Progreso</TableHead>
                                    <TableHead>Estado</TableHead>
                                    <TableHead class="text-right">Acciones</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="product in products" :key="product.id" class="crm-table-row-action">
                                    <TableCell>
                                        <Checkbox
                                            :checked="selectedIds.includes(product.id)"
                                            @update:checked="() => toggleSelection(product.id)"
                                        />
                                    </TableCell>
                                    <TableCell>
                                        <p class="font-medium">{{ product.name }}</p>
                                        <p class="text-xs text-muted-foreground">ID {{ product.id }}</p>
                                    </TableCell>
                                    <TableCell class="text-sm text-muted-foreground">
                                        {{ progressLabel(product) }}
                                    </TableCell>
                                    <TableCell>
                                        <Badge :variant="embeddingStatus(product).variant">
                                            {{ embeddingStatus(product).label }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell class="text-right">
                                        <div class="flex justify-end gap-1">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                class="h-8"
                                                :disabled="processing"
                                                @click="handleProcessProduct(product.id)"
                                            >
                                                <Sparkles class="mr-1 h-3.5 w-3.5" />
                                                Procesar
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                class="h-8 w-8 text-destructive hover:text-destructive"
                                                :disabled="processing"
                                                @click="handleClearProduct(product.id)"
                                            >
                                                <Trash2 class="h-4 w-4" />
                                                <span class="sr-only">Limpiar embeddings</span>
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>

                    <CrmPagination
                        v-if="pagination.last_page > 1"
                        :page="pagination.current_page"
                        :last-page="pagination.last_page"
                        :total="pagination.total"
                        :from="fromItem"
                        :to="toItem"
                        :disabled="loading"
                        @update:page="goToPage"
                    />
                </template>
            </CrmListCard>
        </div>
    </AppLayout>
</template>
