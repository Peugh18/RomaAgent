<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import CrmPanel from '@/components/crm/CrmPanel.vue';
import PageHeader from '@/components/crm/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { 
    Plus, 
    Search, 
    Edit, 
    Trash2, 
    ChevronLeft, 
    ChevronRight, 
    Package 
} from 'lucide-vue-next';
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
const searchQuery = ref('');
const currentPage = ref(1);
const itemsPerPage = ref(20);
let searchDebounceTimer: ReturnType<typeof setTimeout> | null = null;

const fetchProducts = async () => {
    loading.value = true;
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
        console.error('Error fetching products:', error);
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
        return '—';
    }

    return formatDiscount(Number(product.price), Number(product.discount));
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

            <!-- Search and Filter Bar -->
            <div class="mb-4 flex items-center justify-between gap-4 flex-wrap">
                <div class="relative w-full max-w-xs">
                    <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="searchQuery" placeholder="Buscar producto..." class="pl-9" />
                </div>
            </div>

            <CrmPanel no-padding>
                <div v-if="loading" class="py-20 text-center text-sm text-muted-foreground">Cargando productos…</div>
                
                <div v-else-if="paginatedProducts.length === 0" class="py-20 text-center text-sm text-muted-foreground">
                    No se encontraron productos registrados.
                </div>
                
                <div v-else>
                    <div class="overflow-x-auto">
                        <table class="crm-table">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Categoría</th>
                                    <th>Variantes</th>
                                    <th>Precio</th>
                                    <th>Promo activa</th>
                                    <th class="text-right !text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr v-for="product in paginatedProducts" :key="product.id">
                                    <td class="font-medium text-foreground">
                                        <div class="flex items-center gap-2">
                                            <Package class="h-4 w-4 text-primary" />
                                            <span>{{ product.name }}</span>
                                        </div>
                                    </td>
                                    <td class="text-muted-foreground">{{ product.category?.name || 'Sin categoría' }}</td>
                                    <td class="text-muted-foreground">{{ product.variants.length }}</td>
                                    <td
                                        :class="
                                            !product.price || Number(product.price) <= 0
                                                ? 'font-bold text-destructive'
                                                : 'font-bold text-emerald-600 dark:text-emerald-400'
                                        "
                                    >
                                        {{ formatPrice(product.price, 'Sin precio') }}
                                    </td>
                                    <td class="text-muted-foreground">
                                        {{ formatPromoLabel(product) }}
                                    </td>
                                    <td class="text-right">
                                        <div class="flex justify-end gap-1.5">
                                            <Button 
                                                variant="ghost" 
                                                size="sm" 
                                                as-child 
                                                class="h-8 px-2 text-primary hover:text-primary hover:bg-primary/10"
                                            >
                                                <Link :href="`/productos/${product.id}/edit`">
                                                    <Edit class="h-3.5 w-3.5" />
                                                    <span class="sr-only">Editar</span>
                                                </Link>
                                            </Button>
                                            
                                            <Button 
                                                variant="ghost" 
                                                size="sm" 
                                                class="h-8 px-2 text-destructive hover:text-destructive hover:bg-destructive/10" 
                                                @click="deleteProduct(product.id)"
                                            >
                                                <Trash2 class="h-3.5 w-3.5" />
                                                <span class="sr-only">Eliminar</span>
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination Footer -->
                    <div class="flex items-center justify-between border-t border-border px-5 py-4 bg-muted/10 flex-wrap gap-4">
                        <div class="text-xs text-muted-foreground">
                            Mostrando <span class="font-semibold">{{ fromItem }}</span> a <span class="font-semibold">{{ toItem }}</span> de <span class="font-semibold">{{ totalItems }}</span> productos
                        </div>
                        <div class="flex items-center gap-1">
                            <Button 
                                variant="outline" 
                                size="icon"
                                class="h-8 w-8"
                                :disabled="currentPage === 1" 
                                @click="currentPage--"
                            >
                                <ChevronLeft class="h-4 w-4" />
                                <span class="sr-only">Anterior</span>
                            </Button>
                            
                            <Button 
                                v-for="page in totalPages" 
                                :key="page" 
                                size="sm" 
                                class="h-8 w-8 p-0"
                                :variant="currentPage === page ? 'default' : 'outline'"
                                @click="currentPage = page"
                            >
                                {{ page }}
                            </Button>
                            
                            <Button 
                                variant="outline" 
                                size="icon"
                                class="h-8 w-8"
                                :disabled="currentPage === totalPages" 
                                @click="currentPage++"
                            >
                                <ChevronRight class="h-4 w-4" />
                                <span class="sr-only">Siguiente</span>
                            </Button>
                        </div>
                    </div>
                </div>
            </CrmPanel>
        </div>
    </AppLayout>
</template>
