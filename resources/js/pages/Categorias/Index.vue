<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import CrmAlert from '@/components/crm/CrmAlert.vue';
import CrmListCard from '@/components/crm/CrmListCard.vue';
import CrmPagination from '@/components/crm/CrmPagination.vue';
import CrmSearchBar from '@/components/crm/CrmSearchBar.vue';
import CrmAnimatedSection from '@/components/crm/CrmAnimatedSection.vue';
import CrmPageHero from '@/components/crm/CrmPageHero.vue';
import CategoryEmptyState from '@/components/categories/CategoryEmptyState.vue';
import CategoryTableSkeleton from '@/components/categories/CategoryTableSkeleton.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { apiJson, ApiError } from '@/composables/useApi';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { Plus, Trash2, FolderOpen } from 'lucide-vue-next';
import { ref, computed, watch, onMounted } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Categorías',
        href: '/categorias',
    },
];

interface Category {
    id: number;
    name: string;
    slug: string;
}

const categories = ref<Category[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);
const showCreateModal = ref(false);
const newCategoryName = ref('');
const isCreating = ref(false);

// Search and Pagination State
const searchQuery = ref('');
const currentPage = ref(1);
const itemsPerPage = 10;

const fetchCategories = async () => {
    loading.value = true;
    error.value = null;
    try {
        categories.value = await apiJson<Category[]>('/api/categories');
    } catch (e) {
        error.value = e instanceof ApiError ? e.message : 'No se pudieron cargar las categorías.';
        categories.value = [];
    } finally {
        loading.value = false;
    }
};

// Filter categories based on search query
const filteredCategories = computed(() => {
    if (!searchQuery.value) return categories.value;
    return categories.value.filter((category) =>
        category.name.toLowerCase().includes(searchQuery.value.toLowerCase())
    );
});

// Calculate total pages
const totalPages = computed(() => {
    return Math.max(1, Math.ceil(filteredCategories.value.length / itemsPerPage));
});

const fromItem = computed(() => {
    if (filteredCategories.value.length === 0) {
        return 0;
    }

    return (currentPage.value - 1) * itemsPerPage + 1;
});

const toItem = computed(() =>
    Math.min(currentPage.value * itemsPerPage, filteredCategories.value.length),
);

const paginatedCategories = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    return filteredCategories.value.slice(start, start + itemsPerPage);
});

// Reset page to 1 when search query changes
watch(searchQuery, () => {
    currentPage.value = 1;
});

const createCategory = async () => {
    if (!newCategoryName.value.trim() || isCreating.value) return;

    isCreating.value = true;
    try {
        await apiJson('/api/categories', {
            method: 'POST',
            body: JSON.stringify({ name: newCategoryName.value }),
        });
        newCategoryName.value = '';
        showCreateModal.value = false;
        await fetchCategories();
    } catch (e) {
        const msg = e instanceof ApiError ? e.message : 'Error al crear la categoría';
        alert(msg);
    } finally {
        isCreating.value = false;
    }
};

const deleteCategory = async (id: number) => {
    if (!confirm('¿Estás seguro de eliminar esta categoría?')) return;
    try {
        await apiJson(`/api/categories/${id}`, { method: 'DELETE' });
        categories.value = categories.value.filter((c) => c.id !== id);
    } catch (e) {
        const msg = e instanceof ApiError ? e.message : 'Error al eliminar la categoría';
        alert(msg);
    }
};

onMounted(() => {
    fetchCategories();
});
</script>

<template>
    <Head title="Categorías" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="crm-page">
            <CrmPageHero
                title="Categorías"
                description="Organiza el catálogo de productos."
                :icon="FolderOpen"
                variant="amber"
                :stats="[{ label: 'Total', value: categories.length }]"
            >
                <template #actions>
                    <Button class="gap-2 bg-emerald-600 hover:bg-emerald-700" @click="showCreateModal = true">
                        <Plus class="h-4 w-4" />
                        Nueva categoría
                    </Button>
                </template>
            </CrmPageHero>

            <CrmAlert v-if="error">{{ error }}</CrmAlert>

            <CrmAnimatedSection :delay="80">
            <div class="crm-toolbar">
                <CrmSearchBar v-model="searchQuery" placeholder="Buscar categoría…" :disabled="loading" />
            </div>

            <CrmListCard>
                <CategoryTableSkeleton v-if="loading" />

                <CategoryEmptyState
                    v-else-if="filteredCategories.length === 0"
                    :search-query="searchQuery"
                    @create="showCreateModal = true"
                />

                <template v-else>
                    <div class="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow class="hover:bg-transparent">
                                    <TableHead class="w-[400px]">Nombre</TableHead>
                                    <TableHead>Slug</TableHead>
                                    <TableHead class="w-[100px] text-right">Acciones</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="category in paginatedCategories" :key="category.id" class="crm-table-row-action">
                                    <TableCell>
                                        <div class="flex items-center gap-3">
                                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-muted">
                                                <FolderOpen class="h-4 w-4 text-primary" />
                                            </div>
                                            <span class="font-medium">{{ category.name }}</span>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant="outline" class="font-mono text-xs">
                                            {{ category.slug }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell class="text-right">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="h-8 w-8 text-destructive hover:text-destructive"
                                            @click="deleteCategory(category.id)"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                            <span class="sr-only">Eliminar</span>
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>

                    <CrmPagination
                        :page="currentPage"
                        :last-page="totalPages"
                        :total="filteredCategories.length"
                        :from="fromItem"
                        :to="toItem"
                        :disabled="loading"
                        @update:page="currentPage = $event"
                    />
                </template>
            </CrmListCard>
            </CrmAnimatedSection>
        </div>

        <!-- Modal para crear categoría -->
        <Dialog :open="showCreateModal" @update:open="showCreateModal = $event">
            <DialogContent class="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle>Nueva Categoría</DialogTitle>
                    <DialogDescription>
                        Ingresa el nombre de la nueva categoría para tu catálogo.
                    </DialogDescription>
                </DialogHeader>
                
                <div class="grid gap-4 py-4">
                    <div class="grid gap-2">
                        <Label for="category-name">Nombre</Label>
                        <Input
                            id="category-name"
                            v-model="newCategoryName"
                            placeholder="Ej. Vestidos, Conjuntos..."
                            @keyup.enter="createCategory"
                            :disabled="isCreating"
                            autocomplete="off"
                        />
                    </div>
                </div>
                
                <DialogFooter>
                    <Button variant="outline" @click="showCreateModal = false" :disabled="isCreating">
                        Cancelar
                    </Button>
                    <Button @click="createCategory" :disabled="!newCategoryName.trim() || isCreating">
                        {{ isCreating ? 'Creando...' : 'Crear categoría' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
