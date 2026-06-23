<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import CrmAlert from '@/components/crm/CrmAlert.vue';
import CrmListCard from '@/components/crm/CrmListCard.vue';
import CrmPagination from '@/components/crm/CrmPagination.vue';
import CrmSearchBar from '@/components/crm/CrmSearchBar.vue';
import CrmAnimatedSection from '@/components/crm/CrmAnimatedSection.vue';
import CrmEmptyState from '@/components/crm/CrmEmptyState.vue';
import CrmPageHero from '@/components/crm/CrmPageHero.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed, onMounted, watch, onUnmounted } from 'vue';
import { getInitials } from '@/composables/useInitials';
import { format } from 'date-fns';
import { es } from 'date-fns/locale';
import { Users, MessageCircle, Edit, Save, X, Loader2, ShoppingBag, Eye, CreditCard } from 'lucide-vue-next';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Clientes', href: '/clientes' }];

interface Sale {
    id: number;
    product_name: string;
    total_amount: number;
    status: string;
    created_at: string;
    payment_method?: string | null;
    customer_data?: Record<string, any> | null;
}

interface Customer {
    id: number;
    phone_number: string;
    name: string | null;
    notes: string | null;
    ia_paused: boolean;
    ia_pause_reason: string | null;
    active_sale_id: number | null;
    last_inbound_at: string | null;
    created_at: string;
    sales_count: number;
    total_spent: number;
    recent_sales: Sale[];
}

interface PaginatedResponse<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

const customers = ref<Customer[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);
const searchQuery = ref('');
const currentPage = ref(1);
const itemsPerPage = ref(20);
const lastPage = ref(1);
const totalCustomers = ref(0);
let searchDebounceTimer: ReturnType<typeof setTimeout> | null = null;

// Edit modal state
const editingCustomer = ref<Customer | null>(null);
const editNotes = ref('');
const saving = ref(false);

// View sales modal state
const viewingCustomer = ref<Customer | null>(null);

const fetchCustomers = async () => {
    loading.value = true;
    error.value = null;
    try {
        const params = new URLSearchParams({
            page: String(currentPage.value),
            per_page: String(itemsPerPage.value),
        });
        if (searchQuery.value.trim()) {
            params.set('search', searchQuery.value.trim());
        }

        const raw = await apiJson<unknown>(`/api/customers?${params.toString()}`);
        const response = normalizeLaravelPagination<Customer>(raw);
        customers.value = response.data;
        currentPage.value = response.current_page;
        lastPage.value = response.last_page;
        itemsPerPage.value = response.per_page;
        totalCustomers.value = response.total;
    } catch (e) {
        error.value = e instanceof ApiError ? e.message : 'No se pudieron cargar los clientes.';
        customers.value = [];
    } finally {
        loading.value = false;
    }
};

const goToPage = (page: number) => {
    if (page < 1 || page > lastPage.value) return;
    currentPage.value = page;
    fetchCustomers();
};

const paginatedCustomers = computed(() => customers.value);
const totalPages = computed(() => lastPage.value);

const fromItem = computed(() => {
    if (totalCustomers.value === 0) {
        return 0;
    }

    return (currentPage.value - 1) * itemsPerPage.value + 1;
});

const toItem = computed(() =>
    Math.min(currentPage.value * itemsPerPage.value, totalCustomers.value),
);

watch(searchQuery, () => {
    if (searchDebounceTimer) {
        clearTimeout(searchDebounceTimer);
    }
    currentPage.value = 1;
    searchDebounceTimer = setTimeout(() => {
        void fetchCustomers();
    }, 300);
});

onUnmounted(() => {
    if (searchDebounceTimer) {
        clearTimeout(searchDebounceTimer);
    }
});

const openEditModal = (customer: Customer) => {
    editingCustomer.value = customer;
    editNotes.value = customer.notes || '';
};

const closeEditModal = () => {
    editingCustomer.value = null;
    editNotes.value = '';
};

const saveNotes = async () => {
    if (!editingCustomer.value) return;

    saving.value = true;
    try {
        await apiJson(`/api/customers/${editingCustomer.value.id}`, {
            method: 'PUT',
            body: JSON.stringify({ notes: editNotes.value.trim() || null }),
        });

        // Update local state
        const index = customers.value.findIndex(c => c.id === editingCustomer.value!.id);
        if (index !== -1) {
            customers.value[index].notes = editNotes.value.trim() || null;
        }

        closeEditModal();
    } catch (e) {
        alert(e instanceof ApiError ? e.message : 'Error al guardar notas');
    } finally {
        saving.value = false;
    }
};

const openViewSalesModal = (customer: Customer) => {
    viewingCustomer.value = customer;
};

const closeViewSalesModal = () => {
    viewingCustomer.value = null;
};

const formatDate = (date: string): string =>
    format(new Date(date), 'dd MMM yyyy', { locale: es });

onMounted(() => {
    fetchCustomers();
});
</script>

<template>
    <Head title="Clientes" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="crm-page">
            <CrmPageHero
                title="Clientes"
                description="Directorio de contactos de WhatsApp con notas internas para el asesor."
                :icon="Users"
                variant="sky"
                :stats="[{ label: 'Registrados', value: totalCustomers }]"
            />

            <CrmAlert v-if="error" class="mb-0">{{ error }}</CrmAlert>

            <CrmAnimatedSection :delay="80">
            <div class="crm-toolbar">
                <CrmSearchBar
                    v-model="searchQuery"
                    placeholder="Buscar por nombre o teléfono…"
                    :disabled="loading"
                />
            </div>

            <CrmListCard>
                <!-- Loading -->
                <div v-if="loading" class="flex items-center justify-center py-16">
                    <Loader2 class="h-6 w-6 animate-spin text-muted-foreground" />
                </div>

                <!-- Empty -->
                <CrmEmptyState
                    v-else-if="paginatedCustomers.length === 0"
                    :icon="Users"
                    :title="searchQuery ? 'No se encontraron clientes' : 'Sin clientes registrados'"
                    :description="searchQuery
                        ? `No hay resultados para «${searchQuery}»`
                        : 'Los clientes se registran automáticamente cuando interactúan por WhatsApp.'"
                />

                <!-- Table -->
                <template v-else>
                    <div class="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow class="hover:bg-transparent">
                                    <TableHead class="w-[180px]">Teléfono</TableHead>
                                    <TableHead class="w-[180px]">Nombre</TableHead>
                                    <TableHead>Notas internas</TableHead>
                                    <TableHead class="w-[100px] text-center">Compras</TableHead>
                                    <TableHead class="w-[100px] text-center">Estado</TableHead>
                                    <TableHead class="w-[120px] text-right">Acciones</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="customer in paginatedCustomers"
                                    :key="customer.id"
                                    class="crm-table-row-action"
                                >
                                    <TableCell>
                                        <div class="flex items-center gap-3">
                                            <Avatar class="h-9 w-9 border border-border/60">
                                                <AvatarFallback class="bg-muted text-xs font-semibold">
                                                    {{ getInitials(customer.name || customer.phone_number) }}
                                                </AvatarFallback>
                                            </Avatar>
                                            <span class="font-mono text-sm">{{ customer.phone_number }}</span>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <span v-if="customer.name" class="font-medium">{{ customer.name }}</span>
                                        <span v-else class="text-muted-foreground italic">Sin nombre</span>
                                    </TableCell>
                                    <TableCell>
                                        <p v-if="customer.notes" class="text-sm line-clamp-2 max-w-md">{{ customer.notes }}</p>
                                        <p v-else class="text-sm text-muted-foreground italic">Sin notas</p>
                                    </TableCell>
                                    <TableCell class="text-center">
                                        <Button
                                            v-if="customer.sales_count > 0"
                                            variant="ghost"
                                            size="sm"
                                            class="h-7 gap-1 text-xs"
                                            @click="openViewSalesModal(customer)"
                                        >
                                            <ShoppingBag class="h-3.5 w-3.5" />
                                            {{ customer.sales_count }}
                                        </Button>
                                        <span v-else class="text-sm text-muted-foreground">—</span>
                                    </TableCell>
                                    <TableCell class="text-center">
                                        <Badge
                                            v-if="customer.ia_paused"
                                            variant="outline"
                                            class="border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-300"
                                        >
                                            IA Pausada
                                        </Badge>
                                        <Badge v-else variant="secondary">Activo</Badge>
                                    </TableCell>
                                    <TableCell class="text-right">
                                        <div class="flex justify-end gap-1">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                class="h-8 w-8"
                                                as-child
                                            >
                                                <Link :href="`/chat?phone=${encodeURIComponent(customer.phone_number)}`">
                                                    <MessageCircle class="h-4 w-4" />
                                                </Link>
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                class="h-8 w-8"
                                                @click="openViewSalesModal(customer)"
                                                :disabled="customer.sales_count === 0"
                                            >
                                                <Eye class="h-4 w-4" />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                class="h-8 w-8"
                                                @click="openEditModal(customer)"
                                            >
                                                <Edit class="h-4 w-4" />
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
                        :total="totalCustomers"
                        :from="fromItem"
                        :to="toItem"
                        :disabled="loading"
                        @update:page="goToPage"
                    />
                </template>
            </CrmListCard>
            </CrmAnimatedSection>
        </div>

        <!-- Edit Notes Modal -->
        <Dialog :open="!!editingCustomer" @update:open="!$event && closeEditModal()">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Notas del cliente</DialogTitle>
                    <DialogDescription>
                        Estas notas son internas y solo las ve el asesor. La IA no tiene acceso a ellas.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4 py-4">
                    <div v-if="editingCustomer" class="space-y-1">
                        <Label class="text-xs text-muted-foreground">Cliente</Label>
                        <p class="font-medium">{{ editingCustomer.name || 'Sin nombre' }}</p>
                        <p class="font-mono text-sm text-muted-foreground">{{ editingCustomer.phone_number }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label for="notes">Notas internas</Label>
                        <Textarea
                            id="notes"
                            v-model="editNotes"
                            placeholder="Ej: Cliente preferencial, siempre paga puntual. Le gustan los colores oscuros..."
                            :rows="5"
                        />
                        <p class="text-xs text-muted-foreground">
                            Estas notas se muestran al asesor humano en el panel de chat.
                        </p>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="closeEditModal" :disabled="saving">
                        <X class="h-4 w-4 mr-2" />
                        Cancelar
                    </Button>
                    <Button @click="saveNotes" :disabled="saving">
                        <Save v-if="!saving" class="h-4 w-4 mr-2" />
                        <Loader2 v-else class="h-4 w-4 mr-2 animate-spin" />
                        {{ saving ? 'Guardando...' : 'Guardar notas' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- View Sales History Modal -->
        <Dialog :open="!!viewingCustomer" @update:open="!$event && closeViewSalesModal()">
            <DialogContent class="sm:max-w-2xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <ShoppingBag class="h-5 w-5" />
                        Perfil e Historial de compras
                    </DialogTitle>
                    <DialogDescription v-if="viewingCustomer">
                        {{ viewingCustomer.name || 'Sin nombre' }} — {{ viewingCustomer.phone_number }}
                    </DialogDescription>
                </DialogHeader>

                <div v-if="viewingCustomer" class="py-4 space-y-6">
                    <!-- Perfil General (Extraído del último pedido) -->
                    <div v-if="viewingCustomer.recent_sales?.length && viewingCustomer.recent_sales[0].customer_data" class="rounded-lg border bg-muted/20 p-4">
                        <h4 class="text-sm font-semibold mb-3 flex items-center gap-1.5">
                            <Users class="h-4 w-4 text-primary" />
                            Últimos datos de contacto y envío
                        </h4>
                        
                        <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm mb-3">
                            <template v-for="([k, v]) in Object.entries(viewingCustomer.recent_sales[0].customer_data).filter(([k, v]) => !['nombre', 'name', 'nombre_completo', 'celular', 'phone', 'maps_url', 'latitude', 'longitude', 'tipo_envio', 'costo_referencial', 'distrito', 'provincia', 'departamento', 'direccion', 'sede_shalom', 'dni'].includes(k) && v)" :key="k">
                                <dt class="text-muted-foreground capitalize">{{ k.replace(/_/g, ' ') }}</dt>
                                <dd class="font-medium break-words">{{ v }}</dd>
                            </template>
                        </dl>

                        <!-- Bloque de Envío -->
                        <div v-if="viewingCustomer.recent_sales[0].customer_data.tipo_envio || viewingCustomer.recent_sales[0].customer_data.distrito || viewingCustomer.recent_sales[0].customer_data.direccion || viewingCustomer.recent_sales[0].customer_data.sede_shalom" class="mt-3 pt-3 border-t border-border/50">
                            <p class="text-xs font-semibold text-muted-foreground mb-2">PREFERENCIA DE ENVÍO</p>
                            
                            <template v-if="viewingCustomer.recent_sales[0].customer_data.tipo_envio === 'motorizado' || (!viewingCustomer.recent_sales[0].customer_data.tipo_envio && (viewingCustomer.recent_sales[0].customer_data.direccion || viewingCustomer.recent_sales[0].customer_data.address))">
                                <dl class="grid grid-cols-2 gap-x-4 gap-y-1.5 text-sm">
                                    <dt class="text-muted-foreground">Tipo</dt>
                                    <dd class="font-medium flex items-center gap-1">🛵 Motorizado <span v-if="!viewingCustomer.recent_sales[0].customer_data.tipo_envio" class="text-xs text-amber-600 font-normal">(Inferido)</span></dd>
                                    <dt v-if="viewingCustomer.recent_sales[0].customer_data.distrito" class="text-muted-foreground">Distrito</dt>
                                    <dd v-if="viewingCustomer.recent_sales[0].customer_data.distrito">{{ viewingCustomer.recent_sales[0].customer_data.distrito }}</dd>
                                    <dt v-if="viewingCustomer.recent_sales[0].customer_data.direccion || viewingCustomer.recent_sales[0].customer_data.address" class="text-muted-foreground">Dirección</dt>
                                    <dd v-if="viewingCustomer.recent_sales[0].customer_data.direccion || viewingCustomer.recent_sales[0].customer_data.address">{{ viewingCustomer.recent_sales[0].customer_data.direccion || viewingCustomer.recent_sales[0].customer_data.address }}</dd>
                                </dl>
                            </template>
                            
                            <template v-else-if="viewingCustomer.recent_sales[0].customer_data.tipo_envio === 'shalom' || (!viewingCustomer.recent_sales[0].customer_data.tipo_envio && (viewingCustomer.recent_sales[0].customer_data.provincia || viewingCustomer.recent_sales[0].customer_data.sede_shalom))">
                                <dl class="grid grid-cols-2 gap-x-4 gap-y-1.5 text-sm">
                                    <dt class="text-muted-foreground">Tipo</dt>
                                    <dd class="font-medium flex items-center gap-1">🚚 Shalom <span v-if="!viewingCustomer.recent_sales[0].customer_data.tipo_envio" class="text-xs text-amber-600 font-normal">(Inferido)</span></dd>
                                    <dt v-if="viewingCustomer.recent_sales[0].customer_data.distrito" class="text-muted-foreground">Destino</dt>
                                    <dd v-if="viewingCustomer.recent_sales[0].customer_data.distrito">{{ viewingCustomer.recent_sales[0].customer_data.distrito }} <span v-if="viewingCustomer.recent_sales[0].customer_data.provincia">- {{ viewingCustomer.recent_sales[0].customer_data.provincia }}</span></dd>
                                    <dt v-if="viewingCustomer.recent_sales[0].customer_data.sede_shalom" class="text-muted-foreground">Agencia</dt>
                                    <dd v-if="viewingCustomer.recent_sales[0].customer_data.sede_shalom">{{ viewingCustomer.recent_sales[0].customer_data.sede_shalom }}</dd>
                                    <dt v-if="viewingCustomer.recent_sales[0].customer_data.dni" class="text-muted-foreground">DNI Recojo</dt>
                                    <dd v-if="viewingCustomer.recent_sales[0].customer_data.dni">{{ viewingCustomer.recent_sales[0].customer_data.dni }}</dd>
                                </dl>
                            </template>
                        </div>
                    </div>

                    <!-- Stats -->
                    <div class="flex gap-4">
                        <div class="flex-1 rounded-lg border bg-muted/50 px-4 py-3">
                            <p class="text-xs text-muted-foreground">Total compras</p>
                            <p class="text-2xl font-semibold">{{ viewingCustomer.sales_count }}</p>
                        </div>
                        <div class="flex-1 rounded-lg border bg-muted/50 px-4 py-3">
                            <p class="text-xs text-muted-foreground">Total gastado</p>
                            <p class="text-2xl font-semibold text-emerald-600 dark:text-emerald-400">S/ {{ viewingCustomer.total_spent?.toFixed(2) || '0.00' }}</p>
                        </div>
                    </div>

                    <!-- Sales List -->
                    <div v-if="viewingCustomer.recent_sales?.length" class="space-y-3">
                        <p class="text-sm font-medium text-muted-foreground">Últimas compras</p>
                        <div
                            v-for="sale in viewingCustomer.recent_sales"
                            :key="sale.id"
                            class="flex flex-col gap-2 rounded-lg border p-4 hover:bg-muted/30 transition-colors"
                        >
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="font-semibold text-base">{{ sale.product_name }}</p>
                                    <p class="text-xs text-muted-foreground">{{ formatDate(sale.created_at) }}</p>
                                </div>
                                <div class="text-right flex flex-col items-end gap-1">
                                    <p class="font-bold text-lg">S/ {{ sale.total_amount?.toFixed(2) }}</p>
                                    <Badge variant="outline" class="text-[10px]">{{ sale.status }}</Badge>
                                </div>
                            </div>
                            
                            <!-- Método de Pago -->
                            <div class="mt-2 text-xs border-t pt-3">
                                <span class="text-muted-foreground block mb-0.5">Método de pago:</span>
                                <span class="font-medium inline-flex items-center gap-1">
                                    <CreditCard class="h-3 w-3" />
                                    {{ sale.payment_method || 'No especificado' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div v-else class="rounded-lg border border-dashed py-8 text-center">
                        <p class="text-sm text-muted-foreground">No hay compras registradas</p>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="closeViewSalesModal">
                        Cerrar
                    </Button>
                    <Button v-if="viewingCustomer" as-child>
                        <Link :href="`/chat?phone=${encodeURIComponent(viewingCustomer.phone_number)}`">
                            <MessageCircle class="h-4 w-4 mr-2" />
                            Ir al chat
                        </Link>
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
