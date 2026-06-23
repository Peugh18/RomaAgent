<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import CrmAlert from '@/components/crm/CrmAlert.vue';
import CrmListCard from '@/components/crm/CrmListCard.vue';
import CrmPagination from '@/components/crm/CrmPagination.vue';
import CrmSearchBar from '@/components/crm/CrmSearchBar.vue';
import CrmAnimatedSection from '@/components/crm/CrmAnimatedSection.vue';
import CrmEmptyState from '@/components/crm/CrmEmptyState.vue';
import CrmPageHero from '@/components/crm/CrmPageHero.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { apiJson, ApiError } from '@/composables/useApi';
import { normalizeLaravelPagination } from '@/lib/pagination';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { ref, computed, onMounted, watch, onUnmounted } from 'vue';
import { MapPin, Edit, Trash2, Plus, Loader2, Info } from 'lucide-vue-next';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Configuración', href: '/configuracion/empresa' },
    { title: 'Zonas de Envío', href: '/configuracion/zonas-envio' }
];

interface ZonaEnvio {
    id: number;
    departamento: string;
    provincia: string;
    distrito: string;
    tipo_envio: string;
    costo_referencial: number;
    activo: boolean;
    observaciones: string | null;
    datos_requeridos: any | null;
}

const zonas = ref<ZonaEnvio[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);
const searchQuery = ref('');
const currentPage = ref(1);
const itemsPerPage = ref(20);
const lastPage = ref(1);
const totalItems = ref(0);
const sortField = ref('departamento');
const sortDirection = ref<'asc' | 'desc'>('asc');
let searchDebounceTimer: ReturnType<typeof setTimeout> | null = null;

// Modal State
const isModalOpen = ref(false);
const isEditing = ref(false);
const saving = ref(false);
const form = ref({
    id: 0,
    departamento: 'Lima',
    provincia: 'Lima',
    distrito: '',
    tipo_envio: 'motorizado',
    costo_referencial: 10,
    activo: true,
    observaciones: '',
});

const fetchZonas = async () => {
    loading.value = true;
    error.value = null;
    try {
        const params = new URLSearchParams({
            page: String(currentPage.value),
            per_page: String(itemsPerPage.value),
            sort: sortField.value,
            order: sortDirection.value,
        });
        if (searchQuery.value.trim()) {
            params.set('search', searchQuery.value.trim());
        }

        const raw = await apiJson<unknown>(`/api/zonas-envio?${params.toString()}`);
        const response = normalizeLaravelPagination<ZonaEnvio>(raw);
        zonas.value = response.data;
        currentPage.value = response.current_page;
        lastPage.value = response.last_page;
        itemsPerPage.value = response.per_page;
        totalItems.value = response.total;
    } catch (e) {
        error.value = e instanceof ApiError ? e.message : 'Error al cargar las zonas de envío.';
    } finally {
        loading.value = false;
    }
};

const handleSort = (field: string) => {
    if (sortField.value === field) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortField.value = field;
        sortDirection.value = 'asc';
    }
    currentPage.value = 1;
    fetchZonas();
};

const goToPage = (page: number) => {
    if (page < 1 || page > lastPage.value) return;
    currentPage.value = page;
    fetchZonas();
};

watch(searchQuery, () => {
    if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
    currentPage.value = 1;
    searchDebounceTimer = setTimeout(() => fetchZonas(), 300);
});

onUnmounted(() => {
    if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
});

const openCreateModal = () => {
    isEditing.value = false;
    form.value = {
        id: 0,
        departamento: 'Lima',
        provincia: 'Lima',
        distrito: '',
        tipo_envio: 'motorizado',
        costo_referencial: 10,
        activo: true,
        observaciones: '',
    };
    isModalOpen.value = true;
};

const openEditModal = (zona: ZonaEnvio) => {
    isEditing.value = true;
    form.value = {
        id: zona.id,
        departamento: zona.departamento,
        provincia: zona.provincia,
        distrito: zona.distrito,
        tipo_envio: zona.tipo_envio,
        costo_referencial: zona.costo_referencial,
        activo: zona.activo,
        observaciones: zona.observaciones || '',
    };
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
};

const saveZona = async () => {
    saving.value = true;
    try {
        const url = isEditing.value ? `/api/zonas-envio/${form.value.id}` : `/api/zonas-envio`;
        const method = isEditing.value ? 'PUT' : 'POST';

        await apiJson(url, {
            method,
            body: JSON.stringify(form.value),
        });

        closeModal();
        fetchZonas();
    } catch (e) {
        alert(e instanceof ApiError ? e.message : 'Error al guardar');
    } finally {
        saving.value = false;
    }
};

const toggleActivo = async (zona: ZonaEnvio) => {
    try {
        await apiJson(`/api/zonas-envio/${zona.id}/toggle`, { method: 'POST' });
        zona.activo = !zona.activo;
    } catch (e) {
        alert('Error al cambiar el estado');
    }
};

const deleteZona = async (zona: ZonaEnvio) => {
    if (!confirm(`¿Estás seguro de eliminar el distrito ${zona.distrito}?`)) return;
    try {
        await apiJson(`/api/zonas-envio/${zona.id}`, { method: 'DELETE' });
        fetchZonas();
    } catch (e) {
        alert('Error al eliminar');
    }
};

onMounted(() => {
    fetchZonas();
});

const fromItem = computed(() => totalItems.value === 0 ? 0 : (currentPage.value - 1) * itemsPerPage.value + 1);
const toItem = computed(() => Math.min(currentPage.value * itemsPerPage.value, totalItems.value));

</script>

<template>
    <Head title="Zonas de Envío" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="crm-page">
            <CrmPageHero
                title="Zonas de Envío"
                description="Administra los distritos y tipos de envío disponibles."
                :icon="MapPin"
                variant="sky"
                :stats="[{ label: 'Zonas Registradas', value: totalItems }]"
            >
                <template #actions>
                    <Button @click="openCreateModal">
                        <Plus class="h-4 w-4 mr-2" />
                        Nueva Zona
                    </Button>
                </template>
            </CrmPageHero>

            <CrmAlert v-if="error" class="mb-0">{{ error }}</CrmAlert>

            <div class="bg-blue-50/50 border border-blue-200 rounded-lg p-4 mb-4 flex gap-3 text-sm text-blue-800 dark:bg-blue-950/20 dark:border-blue-900 dark:text-blue-200 mt-4">
                <Info class="h-5 w-5 shrink-0 text-blue-500" />
                <div>
                    <p class="font-semibold mb-1">Criterio de Cobertura y Costos</p>
                    <ul class="list-disc ml-5 space-y-1">
                        <li>Si un distrito <strong>existe</strong> en esta tabla, se usará el tipo asignado (ej. Motorizado) y su costo.</li>
                        <li>Si un distrito <strong>no existe</strong> aquí, se despachará por defecto vía <strong>Shalom</strong>.</li>
                        <li>Los costos aquí mostrados son <span class="font-semibold">referenciales</span> (únicamente informativos).</li>
                    </ul>
                </div>
            </div>

            <CrmAnimatedSection :delay="80">
            <div class="crm-toolbar">
                <CrmSearchBar
                    v-model="searchQuery"
                    placeholder="Buscar por departamento, provincia o distrito…"
                    :disabled="loading"
                />
            </div>

            <CrmListCard>
                <div v-if="loading" class="flex items-center justify-center py-16">
                    <Loader2 class="h-6 w-6 animate-spin text-muted-foreground" />
                </div>

                <CrmEmptyState
                    v-else-if="zonas.length === 0"
                    :icon="MapPin"
                    :title="searchQuery ? 'No se encontraron zonas' : 'Sin zonas registradas'"
                    :description="searchQuery ? `No hay resultados para «${searchQuery}»` : 'Agrega tu primer distrito de cobertura.'"
                />

                <template v-else>
                    <div class="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow class="hover:bg-transparent">
                                    <TableHead class="cursor-pointer" @click="handleSort('departamento')">
                                        Departamento <span v-if="sortField==='departamento'">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                    </TableHead>
                                    <TableHead class="cursor-pointer" @click="handleSort('provincia')">
                                        Provincia <span v-if="sortField==='provincia'">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                    </TableHead>
                                    <TableHead class="cursor-pointer" @click="handleSort('distrito')">
                                        Distrito <span v-if="sortField==='distrito'">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                    </TableHead>
                                    <TableHead class="cursor-pointer" @click="handleSort('tipo_envio')">
                                        Tipo <span v-if="sortField==='tipo_envio'">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                    </TableHead>
                                    <TableHead class="cursor-pointer text-right" @click="handleSort('costo_referencial')">
                                        Costo Ref. <span v-if="sortField==='costo_referencial'">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                    </TableHead>
                                    <TableHead class="cursor-pointer text-center" @click="handleSort('activo')">
                                        Estado <span v-if="sortField==='activo'">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                    </TableHead>
                                    <TableHead class="text-right">Acciones</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="zona in zonas" :key="zona.id">
                                    <TableCell>{{ zona.departamento }}</TableCell>
                                    <TableCell>{{ zona.provincia }}</TableCell>
                                    <TableCell class="font-medium">{{ zona.distrito }}</TableCell>
                                    <TableCell>
                                        <Badge variant="outline" class="capitalize">
                                            {{ zona.tipo_envio.replace('_', ' ') }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell class="text-right">S/ {{ Number(zona.costo_referencial).toFixed(2) }}</TableCell>
                                    <TableCell class="text-center">
                                        <Checkbox
                                            :checked="!!zona.activo"
                                            @update:checked="toggleActivo(zona)"
                                        />
                                    </TableCell>
                                    <TableCell class="text-right">
                                        <div class="flex justify-end gap-1">
                                            <Button variant="ghost" size="icon" class="h-8 w-8" @click="openEditModal(zona)">
                                                <Edit class="h-4 w-4" />
                                            </Button>
                                            <Button variant="ghost" size="icon" class="h-8 w-8 text-destructive" @click="deleteZona(zona)">
                                                <Trash2 class="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>

                    <CrmPagination
                        :page="currentPage"
                        :last-page="lastPage"
                        :total="totalItems"
                        :from="fromItem"
                        :to="toItem"
                        :disabled="loading"
                        @update:page="goToPage"
                    />
                </template>
            </CrmListCard>
            </CrmAnimatedSection>
        </div>

        <Dialog :open="isModalOpen" @update:open="!$event && closeModal()">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{{ isEditing ? 'Editar Zona' : 'Nueva Zona de Envío' }}</DialogTitle>
                    <DialogDescription>
                        Configura el distrito, tipo de envío y costo referencial.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="saveZona" class="space-y-4 py-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="departamento">Departamento</Label>
                            <Input id="departamento" v-model="form.departamento" required />
                        </div>
                        <div class="space-y-2">
                            <Label for="provincia">Provincia</Label>
                            <Input id="provincia" v-model="form.provincia" required />
                        </div>
                    </div>

                    <div class="space-y-2">
                        <Label for="distrito">Distrito</Label>
                        <Input id="distrito" v-model="form.distrito" required />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="tipo_envio">Operador Logístico</Label>
                            <Input id="tipo_envio" v-model="form.tipo_envio" required placeholder="Ej: motorizado, olva..." />
                        </div>
                        <div class="space-y-2">
                            <Label for="costo">Costo Referencial (S/)</Label>
                            <Input id="costo" type="number" step="0.10" min="0" v-model="form.costo_referencial" required />
                        </div>
                    </div>

                    <div class="space-y-2">
                        <Label for="observaciones">Observaciones (Opcional)</Label>
                        <Textarea id="observaciones" v-model="form.observaciones" placeholder="Restricciones de zonas, horarios..." rows="2" />
                    </div>

                    <div class="flex items-center space-x-2 pt-2 border-t mt-4">
                        <Checkbox id="activo" v-model:checked="form.activo" />
                        <Label for="activo">Zona activa</Label>
                    </div>

                    <DialogFooter class="pt-4">
                        <Button type="button" variant="outline" @click="closeModal" :disabled="saving">
                            Cancelar
                        </Button>
                        <Button type="submit" :disabled="saving">
                            <Loader2 v-if="saving" class="h-4 w-4 mr-2 animate-spin" />
                            {{ saving ? 'Guardando...' : 'Guardar' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
