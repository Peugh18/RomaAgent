<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { onMounted, ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Search, ChevronLeft, Loader2, Calendar } from 'lucide-vue-next';
import { usePipelineArchive } from '@/composables/useSale';
import { useCurrency } from '@/composables/useCurrency';

const props = defineProps<{
    status?: string;
}>();

const { format: formatMoney } = useCurrency();

const {
    sales,
    loading,
    page,
    lastPage,
    total,
    search,
    status: archiveStatus,
    period: archivePeriod,
    loadArchive,
} = usePipelineArchive();

const selectedStatus = ref(props.status === 'cancelado' ? 'cancelado' : 'entregado');
const searchInput = ref('');

let debounceTimer: ReturnType<typeof setTimeout>;

const onSearch = () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        search.value = searchInput.value;
        page.value = 1;
        loadArchive();
    }, 500);
};

const nextPage = () => {
    if (page.value < lastPage.value) {
        page.value++;
        loadArchive();
    }
};

const prevPage = () => {
    if (page.value > 1) {
        page.value--;
        loadArchive();
    }
};

watch(selectedStatus, (newVal) => {
    archiveStatus.value = newVal;
    page.value = 1;
    loadArchive();
});

const selectedPeriod = ref('todos');

watch(selectedPeriod, (newVal) => {
    archivePeriod.value = newVal;
    page.value = 1;
    loadArchive();
});

onMounted(() => {
    archiveStatus.value = selectedStatus.value;
    archivePeriod.value = selectedPeriod.value;
    loadArchive();
});

</script>

<template>
    <AppLayout title="Historial del Pipeline">
        <Head title="Historial del Pipeline" />

        <div class="space-y-4">
            <!-- Header -->
            <div class="flex items-center gap-3">
                <Link href="/pipeline">
                    <Button variant="ghost" size="icon" class="h-8 w-8">
                        <ChevronLeft class="h-4 w-4" />
                    </Button>
                </Link>
                <h2 class="text-2xl font-bold tracking-tight">Historial de Ventas</h2>
            </div>

            <!-- Filters -->
            <Card>
                <CardContent class="p-4 flex flex-col sm:flex-row gap-4 items-center justify-between">
                    <div class="flex flex-1 w-full sm:w-auto items-center gap-2">
                        <div class="relative w-full sm:w-80">
                            <Search class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                            <Input
                                v-model="searchInput"
                                @input="onSearch"
                                placeholder="Buscar por cliente, teléfono o producto..."
                                class="pl-9 bg-muted/50 w-full"
                            />
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-3 w-full sm:w-auto">
                        <div class="relative">
                            <Calendar class="absolute left-3 top-2.5 h-4 w-4 text-muted-foreground pointer-events-none" />
                            <select
                                v-model="selectedPeriod"
                                class="h-9 w-[140px] appearance-none rounded-md border border-input bg-background pl-9 pr-8 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <option value="todos">Todo el tiempo</option>
                                <option value="hoy">Hoy</option>
                                <option value="semana">Esta semana</option>
                                <option value="mes">Este mes</option>
                            </select>
                            <ChevronLeft class="absolute right-3 top-3 h-3 w-3 -rotate-90 text-muted-foreground pointer-events-none" />
                        </div>

                        <div class="relative">
                            <select
                                v-model="selectedStatus"
                                class="h-9 w-[140px] appearance-none rounded-md border border-input bg-background px-3 pr-8 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <option value="entregado">Entregados</option>
                                <option value="cancelado">Cancelados</option>
                            </select>
                            <ChevronLeft class="absolute right-3 top-3 h-3 w-3 -rotate-90 text-muted-foreground pointer-events-none" />
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Table content -->
            <Card>
                <div class="relative overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs text-muted-foreground bg-muted/50 uppercase border-b">
                            <tr>
                                <th class="px-4 py-3 font-medium">Cliente / Teléfono</th>
                                <th class="px-4 py-3 font-medium">Producto</th>
                                <th class="px-4 py-3 font-medium">Monto</th>
                                <th class="px-4 py-3 font-medium">Estado</th>
                                <th class="px-4 py-3 font-medium text-right">Fecha</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-if="loading" class="bg-background">
                                <td colspan="5" class="px-4 py-8 text-center">
                                    <Loader2 class="h-6 w-6 animate-spin mx-auto text-muted-foreground" />
                                </td>
                            </tr>
                            <tr v-else-if="sales.length === 0" class="bg-background">
                                <td colspan="5" class="px-4 py-8 text-center text-muted-foreground">
                                    No se encontraron ventas para esta búsqueda.
                                </td>
                            </tr>
                            <tr v-for="sale in sales" :key="sale.id" class="bg-background hover:bg-muted/30 transition-colors">
                                <td class="px-4 py-3">
                                    <div class="font-medium">{{ sale.customer_name ?? sale.customer?.name ?? 'Sin nombre' }}</div>
                                    <div class="text-muted-foreground text-xs">{{ sale.phone_number }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div>{{ sale.product_name }}</div>
                                    <div class="text-muted-foreground text-xs">{{ sale.size }} • {{ sale.color }}</div>
                                </td>
                                <td class="px-4 py-3 font-medium">
                                    {{ formatMoney(Number(sale.total_amount)) }}
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                                        :class="sale.status === 'entregado' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-rose-100 text-rose-800 dark:bg-rose-900/30 dark:text-rose-400'">
                                        {{ sale.status.toUpperCase() }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right text-muted-foreground">
                                    {{ new Date(sale.updated_at).toLocaleDateString() }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <div v-if="lastPage > 1" class="flex items-center justify-between px-4 py-3 border-t bg-muted/20">
                    <div class="text-sm text-muted-foreground">
                        Mostrando página <span class="font-medium text-foreground">{{ page }}</span> de <span class="font-medium text-foreground">{{ lastPage }}</span>
                        ({{ total }} totales)
                    </div>
                    <div class="flex gap-2">
                        <Button variant="outline" size="sm" :disabled="page <= 1 || loading" @click="prevPage">
                            Anterior
                        </Button>
                        <Button variant="outline" size="sm" :disabled="page >= lastPage || loading" @click="nextPage">
                            Siguiente
                        </Button>
                    </div>
                </div>
            </Card>
        </div>
    </AppLayout>
</template>
