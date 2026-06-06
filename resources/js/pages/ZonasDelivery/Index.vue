<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/crm/PageHeader.vue';
import CrmPanel from '@/components/crm/CrmPanel.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { type BreadcrumbItem } from '@/types';
import { apiJson, ApiError } from '@/composables/useApi';
import { Head } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted } from 'vue';
import {
    Search,
    Plus,
    MapPin,
    TrendingUp,
    Truck,
    Edit,
    Trash2,
    ChevronLeft,
    ChevronRight,
    Globe,
} from 'lucide-vue-next';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tarifas de Delivery', href: '/zonas-delivery' },
];

type TipoZona = 'lima' | 'solo_shalom';

interface DeliveryZone {
    id: number;
    district: string;
    cost_motorizado: number;
    cost_shalom: number;
}

const deliveryZones = ref<DeliveryZone[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);
const showCreateModal = ref(false);
const editingZone = ref<DeliveryZone | null>(null);
const tipoZona = ref<TipoZona>('lima');
const form = ref({
    district: '',
    cost_motorizado: 12,
    cost_shalom: 10,
});

const searchQuery = ref('');
const currentPage = ref(1);
const itemsPerPage = 15;

const distritosSugeridos = [
    'Provincia (Shalom)', 'Trujillo', 'Cercado de Lima', 'Breña', 'Jesús María', 'La Victoria', 'Lince',
    'Magdalena del Mar', 'Miraflores', 'Pueblo Libre', 'Rímac', 'San Borja', 'San Isidro', 'San Miguel',
    'Santiago de Surco', 'Surquillo', 'Carabayllo', 'Comas', 'Independencia', 'Los Olivos', 'Puente Piedra',
    'San Martín de Porres', 'Santa Rosa', 'Ancón', 'Ate', 'El Agustino', 'Lurigancho-Chosica',
    'San Juan de Lurigancho', 'Santa Anita', 'Chaclacayo', 'Cieneguilla', 'Barranco', 'Chorrillos', 'Lurín',
    'Pachacámac', 'Pucusana', 'Punta Hermosa', 'Punta Negra', 'San Bartolo', 'San Juan de Miraflores',
    'Santa María del Mar', 'Villa El Salvador', 'Villa María del Triunfo',
];

const esSoloShalom = (zone: DeliveryZone): boolean => Number(zone.cost_motorizado) <= 0;

const formatearMotorizado = (zone: DeliveryZone): string =>
    esSoloShalom(zone) ? 'No aplica' : `S/ ${Number(zone.cost_motorizado).toFixed(2)}`;

const fetchDeliveryZones = async () => {
    loading.value = true;
    error.value = null;
    try {
        deliveryZones.value = await apiJson<DeliveryZone[]>('/api/delivery-zones');
    } catch (e) {
        error.value = e instanceof ApiError ? e.message : 'No se pudieron cargar las zonas.';
        deliveryZones.value = [];
    } finally {
        loading.value = false;
    }
};

const filteredZones = computed(() => {
    let zones = [...deliveryZones.value];

    if (searchQuery.value) {
        zones = zones.filter((zone) =>
            zone.district.toLowerCase().includes(searchQuery.value.toLowerCase()),
        );
    }

    return zones.sort((a, b) => {
        const aSolo = esSoloShalom(a);
        const bSolo = esSoloShalom(b);
        if (aSolo !== bSolo) {
            return aSolo ? -1 : 1;
        }

        return a.district.localeCompare(b.district, 'es');
    });
});

const totalPages = computed(() => Math.max(1, Math.ceil(filteredZones.value.length / itemsPerPage)));

const paginatedZones = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    return filteredZones.value.slice(start, start + itemsPerPage);
});

watch(searchQuery, () => {
    currentPage.value = 1;
});

const resetForm = (tipo: TipoZona = 'lima') => {
    tipoZona.value = tipo;

    form.value =
        tipo === 'solo_shalom'
            ? { district: '', cost_motorizado: 0, cost_shalom: 12 }
            : { district: '', cost_motorizado: 12, cost_shalom: 10 };
};

const openCreateModal = () => {
    editingZone.value = null;
    resetForm('lima');
    showCreateModal.value = true;
};

const openEditModal = (zone: DeliveryZone) => {
    editingZone.value = zone;
    tipoZona.value = esSoloShalom(zone) ? 'solo_shalom' : 'lima';
    form.value = {
        district: zone.district,
        cost_motorizado: Number(zone.cost_motorizado),
        cost_shalom: Number(zone.cost_shalom),
    };
    showCreateModal.value = true;
};

const onTipoZonaChange = (soloShalom: boolean) => {
    const tipo: TipoZona = soloShalom ? 'solo_shalom' : 'lima';
    const distritoActual = form.value.district;
    resetForm(tipo);
    if (distritoActual) {
        form.value.district = distritoActual;
    }
};

const saveZone = async () => {
    const payload = {
        district: form.value.district.trim(),
        cost_motorizado: tipoZona.value === 'solo_shalom' ? 0 : Number(form.value.cost_motorizado),
        cost_shalom: Number(form.value.cost_shalom),
    };

    if (!payload.district) {
        alert('Ingresa el nombre de la zona.');
        return;
    }

    try {
        const url = editingZone.value
            ? `/api/delivery-zones/${editingZone.value.id}`
            : '/api/delivery-zones';
        const method = editingZone.value ? 'PUT' : 'POST';

        await apiJson(url, { method, body: JSON.stringify(payload) });
        showCreateModal.value = false;
        editingZone.value = null;
        await fetchDeliveryZones();
    } catch (e) {
        alert(e instanceof ApiError ? e.message : 'Error al guardar la zona');
    }
};

const deleteZone = async (id: number) => {
    if (!confirm('¿Estás seguro de eliminar esta zona de delivery?')) {
        return;
    }

    try {
        await apiJson(`/api/delivery-zones/${id}`, { method: 'DELETE' });
        deliveryZones.value = deliveryZones.value.filter((z) => z.id !== id);
    } catch (e) {
        alert(e instanceof ApiError ? e.message : 'Error al eliminar la zona');
    }
};

const importRomaStoreZones = async () => {
    if (!confirm('¿Reemplazar todas las zonas con el tarifario Roma Store?')) {
        return;
    }

    try {
        await apiJson('/api/delivery-zones/import-roma-store', { method: 'POST' });
        await fetchDeliveryZones();
    } catch (e) {
        alert(e instanceof ApiError ? e.message : 'Error al importar tarifario Roma Store');
    }
};

onMounted(fetchDeliveryZones);
</script>

<template>
    <Head title="Tarifas de Delivery" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="crm-page space-y-4">
            <PageHeader
                title="Zonas de delivery"
                :description="`${deliveryZones.length} zonas · Motorizado por distrito Lima · Shalom Lima S/ 10 · Provincia solo Shalom`"
            >
                <template #actions>
                    <div class="flex flex-wrap gap-2">
                        <Button variant="secondary" @click="importRomaStoreZones">Importar Roma Store</Button>
                        <Button @click="openCreateModal">
                            <Plus class="mr-1.5 h-4 w-4" />
                            Nueva zona
                        </Button>
                    </div>
                </template>
            </PageHeader>

            <div
                v-if="error"
                class="rounded-lg border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive"
                role="alert"
            >
                {{ error }}
            </div>

            <div class="flex items-center justify-between gap-4 flex-wrap">
                <p class="text-xs text-muted-foreground">
                    Las zonas <strong>Solo Shalom</strong> aparecen primero. Todo se refleja en el prompt de la IA.
                </p>
                <div class="relative w-full max-w-xs">
                    <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="searchQuery" placeholder="Buscar zona..." class="pl-9" />
                </div>
            </div>

            <CrmPanel noPadding>
                <div v-if="loading" class="py-20 text-center text-sm text-muted-foreground">
                    Cargando tarifas...
                </div>

                <div v-else-if="filteredZones.length === 0" class="py-20 text-center text-sm text-muted-foreground">
                    No se encontraron zonas
                </div>

                <div v-else>
                    <div class="overflow-x-auto">
                        <table class="crm-table">
                            <thead>
                                <tr>
                                    <th>Zona / Distrito</th>
                                    <th>Tipo</th>
                                    <th>Motorizado</th>
                                    <th>Shalom</th>
                                    <th class="text-right !text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr
                                    v-for="zone in paginatedZones"
                                    :key="zone.id"
                                    :class="esSoloShalom(zone) ? 'bg-amber-50/40 dark:bg-amber-950/15' : ''"
                                >
                                    <td class="font-medium text-foreground">
                                        <div class="flex items-center gap-2">
                                            <Globe v-if="esSoloShalom(zone)" class="h-4 w-4 text-amber-600 dark:text-amber-400" />
                                            <MapPin v-else class="h-4 w-4 text-primary" />
                                            <span>{{ zone.district }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <span
                                            v-if="esSoloShalom(zone)"
                                            class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-800 dark:bg-amber-900 dark:text-amber-200"
                                        >
                                            Solo Shalom
                                        </span>
                                        <span
                                            v-else
                                            class="rounded-full bg-blue-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-blue-800 dark:bg-blue-900 dark:text-blue-200"
                                        >
                                            Lima
                                        </span>
                                    </td>
                                    <td class="text-muted-foreground">
                                        <div class="flex items-center gap-1.5">
                                            <Truck class="h-4 w-4 text-muted-foreground/75" />
                                            <span :class="esSoloShalom(zone) ? 'italic text-muted-foreground/70' : ''">
                                                {{ formatearMotorizado(zone) }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="text-muted-foreground">
                                        <div class="flex items-center gap-1.5">
                                            <TrendingUp class="h-4 w-4 text-muted-foreground/75" />
                                            <span>S/ {{ Number(zone.cost_shalom).toFixed(2) }}</span>
                                        </div>
                                    </td>
                                    <td class="text-right">
                                        <div class="flex justify-end gap-1.5">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                class="h-8 px-2 text-primary hover:bg-primary/10 hover:text-primary"
                                                @click="openEditModal(zone)"
                                            >
                                                <Edit class="h-3.5 w-3.5" />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                class="h-8 px-2 text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                @click="deleteZone(zone.id)"
                                            >
                                                <Trash2 class="h-3.5 w-3.5" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-4 border-t border-border bg-muted/10 px-5 py-4">
                        <div class="text-xs text-muted-foreground">
                            Mostrando
                            <span class="font-semibold">{{ Math.min(filteredZones.length, (currentPage - 1) * itemsPerPage + 1) }}</span>
                            a
                            <span class="font-semibold">{{ Math.min(filteredZones.length, currentPage * itemsPerPage) }}</span>
                            de
                            <span class="font-semibold">{{ filteredZones.length }}</span>
                            zonas
                        </div>
                        <div class="flex items-center gap-1">
                            <Button variant="outline" size="icon" class="h-8 w-8" :disabled="currentPage === 1" @click="currentPage--">
                                <ChevronLeft class="h-4 w-4" />
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
                            <Button variant="outline" size="icon" class="h-8 w-8" :disabled="currentPage === totalPages" @click="currentPage++">
                                <ChevronRight class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </div>
            </CrmPanel>
        </div>

        <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center bg-background/80 p-4 backdrop-blur-sm">
            <div class="w-full max-w-md overflow-hidden rounded-xl border border-border bg-card shadow-lg">
                <div class="border-b border-border bg-muted/30 px-6 py-4">
                    <h3 class="text-lg font-semibold">{{ editingZone ? 'Editar zona' : 'Nueva zona' }}</h3>
                    <p class="mt-1 text-xs text-muted-foreground">Se guarda en BD y actualiza el prompt de la IA.</p>
                </div>

                <div class="space-y-4 p-6">
                    <div class="flex items-start gap-3 rounded-lg border border-border bg-muted/20 p-3">
                        <Checkbox
                            id="solo-shalom"
                            :checked="tipoZona === 'solo_shalom'"
                            @update:checked="onTipoZonaChange"
                        />
                        <div class="space-y-1">
                            <Label for="solo-shalom" class="cursor-pointer font-medium">Solo Shalom (provincia)</Label>
                            <p class="text-xs text-muted-foreground">Sin motorizado. Ej: Trujillo, Provincia (Shalom).</p>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="district">Nombre de la zona</Label>
                        <Input id="district" v-model="form.district" list="districts-list" placeholder="Ej: Miraflores o Trujillo" />
                        <datalist id="districts-list">
                            <option v-for="district in distritosSugeridos" :key="district" :value="district" />
                        </datalist>
                    </div>

                    <div v-if="tipoZona === 'lima'" class="space-y-1.5">
                        <Label for="cost-motorizado">Costo Motorizado (S/)</Label>
                        <Input id="cost-motorizado" v-model="form.cost_motorizado" type="number" step="0.01" min="0" />
                    </div>

                    <div class="space-y-1.5">
                        <Label for="cost-shalom">Costo Shalom (S/)</Label>
                        <Input id="cost-shalom" v-model="form.cost_shalom" type="number" step="0.01" min="0" />
                    </div>
                </div>

                <div class="flex justify-end gap-3 border-t border-border bg-muted/10 px-6 py-4">
                    <Button variant="outline" @click="showCreateModal = false; editingZone = null">Cancelar</Button>
                    <Button @click="saveZone">{{ editingZone ? 'Actualizar' : 'Crear' }}</Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
