<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import CrmAlert from '@/components/crm/CrmAlert.vue';
import CrmListCard from '@/components/crm/CrmListCard.vue';
import CrmPagination from '@/components/crm/CrmPagination.vue';
import CrmSearchBar from '@/components/crm/CrmSearchBar.vue';
import PageHeader from '@/components/crm/PageHeader.vue';
import ProductEmptyState from '@/components/products/ProductEmptyState.vue';
import ProductTableSkeleton from '@/components/products/ProductTableSkeleton.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { Plus, Edit, Trash2, Package, ImageOff } from 'lucide-vue-next';
import { useCurrency } from '@/composables/useCurrency';
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
}

// Server-side Pagination State
interface PaginatedProducts {
    data: Product[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

const productsData = ref<PaginatedProducts | null>(null);
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
        productsData.value = await response.json();
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

onMounted(() => {
    fetchProducts();
});
</script>

<template>
    <Head title="Productos" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="crm-page">
            <PageHeader title="Productos" description="Inventario, variantes por color y precios del catálogo.">
                <template #actions>
                    <Button as-child>
                        <Link href="/productos/create" class="gap-2">
                            <Plus class="h-4 w-4" />
                            Nuevo producto
                        </Link>
                    </Button>
                </template>
            </PageHeader>

            <CrmAlert v-if="loadError">{{ loadError }}</CrmAlert>

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
                                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-muted">
                                                <img
                                                    v-if="getFirstVariantImage(product)"
                                                    :src="getFirstVariantImage(product)!"
                                                    :alt="product.name"
                                                    class="h-full w-full rounded-md object-cover"
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
                                    <TableCell class="text-center">
                                        <Badge variant="outline" class="font-mono">{{ product.variants.length }}</Badge>
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
        </div>
    </AppLayout>
</template>
