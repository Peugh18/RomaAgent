<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import CrmAlert from '@/components/crm/CrmAlert.vue';
import CrmListCard from '@/components/crm/CrmListCard.vue';
import CrmPagination from '@/components/crm/CrmPagination.vue';
import CrmSearchBar from '@/components/crm/CrmSearchBar.vue';
import CrmAnimatedSection from '@/components/crm/CrmAnimatedSection.vue';
import CrmPageHero from '@/components/crm/CrmPageHero.vue';
import ProductEmptyState from '@/components/products/ProductEmptyState.vue';
import ProductTableSkeleton from '@/components/products/ProductTableSkeleton.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { Plus, Edit, Trash2, Package, ImageOff, Brain } from 'lucide-vue-next';
import { useCurrency } from '@/composables/useCurrency';
import { normalizeLaravelPagination, type PaginatedResponse } from '@/lib/pagination';
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Productos', href: '/productos' }];

interface Product {
    id: number;
    name: string;
    description: string | null;
    price: number | string | null;
    price_tiktok?: number | string | null;
    discount: number | string | null;
    discount_active?: boolean;
    category_id: number | null;
    tags_ia: string[] | null;
    category: { id: number; name: string } | null;
    variants: ProductVariant[];
}

interface ProductVariant {
    id: number;
    color: string;
    image_url: string | null;
    sizes_stock: Record<string, number>;
    has_embedding?: boolean;
}

const productsData = ref<PaginatedResponse<Product> | null>(null);
const loading = ref(true);
const loadError = ref<string | null>(null);
const searchQuery = ref('');
const currentPage = ref(1);
const itemsPerPage = ref(20);
let searchDebounceTimer: ReturnType<typeof setTimeout> | null = null;

const fetchProducts = async () => {
    loading.value = true;
    loadError.value = null;
    try {
        const params = new URLSearchParams();
        params.append('page', String(currentPage.value));
        params.append('per_page', String(itemsPerPage.value));
        if (searchQuery.value.trim()) {
            params.append('search', searchQuery.value.trim());
        }

        const response = await fetch(`/api/products?${params.toString()}`, {
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) throw new Error('Error loading products');
        productsData.value = normalizeLaravelPagination<Product>(await response.json());
    } catch (error) {
        loadError.value = error instanceof Error ? error.message : 'No se pudieron cargar los productos.';
        productsData.value = null;
    } finally {
        loading.value = false;
    }
};

// Computed helpers for pagination
const paginatedProducts = computed(() => productsData.value?.data ?? []);
const totalPages = computed(() => productsData.value?.last_page ?? 1);
const totalItems = computed(() => productsData.value?.total ?? 0);
const fromItem = computed(() => {
    if (!productsData.value) return 0;
    return (currentPage.value - 1) * itemsPerPage.value + 1;
});
const toItem = computed(() => {
    if (!productsData.value) return 0;
    return Math.min(fromItem.value + paginatedProducts.value.length - 1, totalItems.value);
});

const pageVariantsCount = computed(() =>
    paginatedProducts.value.reduce((sum, product) => sum + product.variants.length, 0),
);

const heroStats = computed(() => {
    const stats = [{ label: 'Total', value: totalItems.value }];

    if (searchQuery.value.trim()) {
        stats.push({ label: 'Filtrados', value: paginatedProducts.value.length });
    } else if (pageVariantsCount.value > 0) {
        stats.push({ label: 'Variantes', value: pageVariantsCount.value });
    }

    return stats;
});

// Debounced search: reset page to 1 and fetch
watch(searchQuery, () => {
    if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
    currentPage.value = 1;
    searchDebounceTimer = setTimeout(() => {
        fetchProducts();
    }, 300);
});

watch(currentPage, () => {
    fetchProducts();
});

onUnmounted(() => {
    if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
});

const { format: formatPrice, formatDiscount } = useCurrency();

const getCsrfToken = (): string => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

const formatPromoLabel = (product: Product): string => {
    if (!product.discount_active || !product.discount || Number(product.discount) <= 0 || !product.price) {
        return '';
    }
    return formatDiscount(Number(product.price), Number(product.discount));
};

const getFirstVariantImage = (product: Product): string | null => {
    const variantWithImage = product.variants.find(v => v.image_url || v.image_path);
    return variantWithImage?.image_url || variantWithImage?.image_path || null;
};

const getVariantStock = (v: ProductVariant): number => {
    return Object.values(v.sizes_stock || {}).reduce((sum, val) => sum + Number(val), 0);
};

const getColorCircleStyle = (colorName: string): Record<string, string> => {
    const name = colorName.toLowerCase().trim();
    const map: Record<string, string> = {
        negro: '#000000',
        blanco: '#ffffff',
        rojo: '#ef4444',
        azul: '#3b82f6',
        verde: '#22c55e',
        amarillo: '#eab308',
        rosa: '#ec4899',
        rosado: '#ec4899',
        fucsia: '#d946ef',
        morado: '#a855f7',
        purpura: '#a855f7',
        celeste: '#06b6d4',
        turquesa: '#06b6d4',
        gris: '#6b7280',
        plomo: '#6b7280',
        marron: '#78350f',
        cafe: '#78350f',
        beige: '#f5f5dc',
        crema: '#fffdd0',
        naranja: '#f97316',
        anaranjado: '#f97316',
        lila: '#d8b4fe',
        vino: '#800020',
        burgundy: '#800020',
        mostaza: '#d97706',
    };
    const hex = map[name] || '#cbd5e1';
    return {
        backgroundColor: hex,
        border: hex === '#ffffff' || hex === '#fffdd0' || hex === '#f5f5dc' ? '1px solid #94a3b8' : 'none',
    };
};

const deleteProduct = async (id: number) => {
    if (!confirm('¿Eliminar este producto?')) return;
    try {
        const response = await fetch(`/api/products/${id}`, {
            method: 'DELETE',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
            credentials: 'same-origin',
        });

        if (response.status === 422) {
            const data = await response.json();
            alert(data.message || 'No se puede eliminar: tiene ventas asociadas');
            return;
        }

        if (!response.ok) throw new Error('Error');

        // Refresh current page after deletion
        await fetchProducts();
    } catch (error) {
        console.error('Error deleting product:', error);
        alert('Error al eliminar el producto');
    }
};

const isGeneratingEmbeddings = ref(false);

const generateEmbeddings = async () => {
    if (!confirm('¿Deseas generar los vectores IA de todas las variantes sin vector? (Esto puede tardar unos minutos)')) return;
    isGeneratingEmbeddings.value = true;
    try {
        const response = await fetch('/api/products/generate-embeddings', {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
            credentials: 'same-origin',
        });
        if (!response.ok) throw new Error('Error al generar vectores');
        const data = await response.json();
        alert(`¡Listo! Procesados: ${data.processed}, Exitosos: ${data.success}, Fallidos: ${data.failed}, Saltados: ${data.skipped}`);
        await fetchProducts();
    } catch (error) {
        console.error(error);
        alert('Error al generar los vectores IA.');
    } finally {
        isGeneratingEmbeddings.value = false;
    }
};

onMounted(() => {
    fetchProducts();
});
</script>

<template>
    <Head title="Productos" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="crm-page">
            <CrmPageHero
                title="Productos"
                description="Inventario, variantes por color y precios del catálogo."
                :icon="Package"
                variant="violet"
                :stats="heroStats"
            >
                <template #actions>
                    <Button variant="outline" class="gap-2 border-emerald-200 text-emerald-700 hover:bg-emerald-50 dark:border-emerald-800 dark:text-emerald-400 dark:hover:bg-emerald-950" @click="generateEmbeddings" :disabled="isGeneratingEmbeddings">
                        <Brain class="h-4 w-4" />
                        {{ isGeneratingEmbeddings ? 'Generando...' : 'Sincronizar IA' }}
                    </Button>
                    <Button as-child class="gap-2 bg-emerald-600 hover:bg-emerald-700">
                        <Link href="/productos/create" class="gap-2">
                            <Plus class="h-4 w-4" />
                            Nuevo producto
                        </Link>
                    </Button>
                </template>
            </CrmPageHero>

            <CrmAlert v-if="loadError">{{ loadError }}</CrmAlert>

            <CrmAnimatedSection :delay="80">
            <div class="crm-toolbar">
                <CrmSearchBar v-model="searchQuery" placeholder="Buscar producto…" :disabled="loading" />
            </div>

            <CrmListCard>
                <ProductTableSkeleton v-if="loading" />
                
                <ProductEmptyState
                    v-else-if="paginatedProducts.length === 0"
                    :search-query="searchQuery"
                />
                
                <template v-else>
                    <div class="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow class="hover:bg-transparent">
                                    <TableHead class="w-[350px]">Producto</TableHead>
                                    <TableHead>Categoría</TableHead>
                                    <TableHead class="w-[100px] text-center">Variantes</TableHead>
                                    <TableHead class="w-[140px] text-right">Precio</TableHead>
                                    <TableHead class="w-[120px] text-center">Promo</TableHead>
                                    <TableHead class="w-[100px] text-right">Acciones</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="product in paginatedProducts" :key="product.id" class="crm-table-row-action">
                                    <TableCell>
                                        <div class="flex items-center gap-3">
                                            <div class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-border/50 bg-muted">
                                                <img
                                                    v-if="getFirstVariantImage(product)"
                                                    :src="getFirstVariantImage(product)!"
                                                    :alt="product.name"
                                                    class="h-full w-full object-cover"
                                                />
                                                <ImageOff v-else class="h-4 w-4 text-muted-foreground" />
                                            </div>
                                            <div class="min-w-0">
                                                <p class="truncate font-medium">{{ product.name }}</p>
                                                <p v-if="product.tags_ia?.length" class="truncate text-xs text-muted-foreground">
                                                    {{ product.tags_ia.slice(0, 2).join(', ') }}
                                                </p>
                                            </div>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <Badge v-if="product.category" variant="secondary" class="font-normal">
                                            {{ product.category.name }}
                                        </Badge>
                                        <span v-else class="text-sm text-muted-foreground">—</span>
                                    </TableCell>
                                    <TableCell class="text-left py-3">
                                        <div class="flex flex-col gap-1.5 max-w-[220px]">
                                            <div
                                                v-for="v in product.variants"
                                                :key="v.id"
                                                class="flex items-center justify-between text-xs py-0.5 px-2 rounded-md border border-border/40 bg-muted/20"
                                            >
                                                <div class="flex items-center gap-1.5 min-w-0">
                                                    <span
                                                        class="h-2 w-2 rounded-full shrink-0"
                                                        :style="getColorCircleStyle(v.color)"
                                                    />
                                                    <span class="font-medium text-foreground truncate max-w-[100px]" :title="v.color">{{ v.color }}</span>
                                                </div>
                                                <span
                                                    :class="[
                                                        'font-mono font-bold px-1 rounded text-[10px]',
                                                        getVariantStock(v) === 0
                                                            ? 'text-red-500 bg-red-50 dark:bg-red-950/30'
                                                            : getVariantStock(v) < 5
                                                                ? 'text-amber-600 bg-amber-50 dark:bg-amber-950/30'
                                                                : 'text-muted-foreground'
                                                    ]"
                                                    :title="Object.entries(v.sizes_stock || {}).map(([size, stock]) => `${size}: ${stock}`).join(', ')"
                                                >
                                                    {{ getVariantStock(v) === 0 ? 'Agotado' : getVariantStock(v) }}
                                                </span>
                                            </div>
                                            <div v-if="product.variants.length === 0" class="text-xs text-muted-foreground italic">
                                                Sin variantes
                                            </div>
                                            <div v-else-if="product.variants.every(v => v.has_embedding)" class="flex items-center gap-1 mt-0.5 text-[10px] text-emerald-500">
                                                <Brain class="h-3 w-3" />
                                                <span>Listos para IA</span>
                                            </div>
                                        </div>
                                    </TableCell>
                                    <TableCell class="text-right">
                                        <span
                                            :class="[
                                                'font-semibold',
                                                !product.price || Number(product.price) <= 0
                                                    ? 'text-destructive'
                                                    : 'text-foreground'
                                            ]"
                                        >
                                            {{ formatPrice(product.price, 'Sin precio') }}
                                        </span>
                                    </TableCell>
                                    <TableCell class="text-center">
                                        <Badge
                                            v-if="formatPromoLabel(product)"
                                            class="bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400"
                                        >
                                            {{ formatPromoLabel(product) }}
                                        </Badge>
                                        <span v-else class="text-sm text-muted-foreground">—</span>
                                    </TableCell>
                                    <TableCell class="text-right">
                                        <div class="flex justify-end gap-1">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                class="h-8 w-8"
                                                as-child
                                            >
                                                <Link :href="`/productos/${product.id}/edit`">
                                                    <Edit class="h-4 w-4" />
                                                    <span class="sr-only">Editar</span>
                                                </Link>
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                class="h-8 w-8 text-destructive hover:text-destructive"
                                                @click="deleteProduct(product.id)"
                                            >
                                                <Trash2 class="h-4 w-4" />
                                                <span class="sr-only">Eliminar</span>
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>

                    <CrmPagination
                        :page="currentPage"
                        :last-page="totalPages"
                        :total="totalItems"
                        :from="fromItem"
                        :to="toItem"
                        :disabled="loading"
                        @update:page="currentPage = $event"
                    />
                </template>
            </CrmListCard>
            </CrmAnimatedSection>
        </div>
    </AppLayout>
</template>
