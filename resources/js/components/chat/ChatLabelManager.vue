<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import axios from 'axios';
import { Tag, Loader2, Check } from 'lucide-vue-next';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    phone: string;
}>();

const emit = defineEmits<{
    'labels-updated': [labels: any[]];
}>();

const labels = ref<any[]>([]);
const customerLabels = ref<number[]>([]);
const loadingLabels = ref(false);
const loadingCustomer = ref(false);
const saving = ref(false);

const loadLabels = async () => {
    loadingLabels.value = true;
    try {
        const response = await axios.get('/api/labels');
        labels.value = response.data;
    } catch (error) {
        console.error('Error cargando etiquetas globales', error);
    } finally {
        loadingLabels.value = false;
    }
};

const loadCustomerLabels = async () => {
    if (!props.phone) return;
    loadingCustomer.value = true;
    try {
        const encoded = encodeURIComponent(props.phone);
        const response = await axios.get(`/api/customers/${encoded}`);
        if (response.data?.labels) {
            customerLabels.value = response.data.labels.map((l: any) => l.id);
        } else {
            customerLabels.value = [];
        }
    } catch (error) {
        console.error('Error cargando etiquetas del cliente', error);
    } finally {
        loadingCustomer.value = false;
    }
};

const toggleLabel = async (labelId: number) => {
    if (!props.phone || saving.value) return;
    
    saving.value = true;
    let newLabels = [...customerLabels.value];
    
    if (newLabels.includes(labelId)) {
        newLabels = newLabels.filter((id) => id !== labelId);
    } else {
        newLabels.push(labelId);
    }
    
    try {
        const encoded = encodeURIComponent(props.phone);
        const response = await axios.post(`/api/customers/${encoded}/labels`, {
            label_ids: newLabels
        });
        customerLabels.value = newLabels;
        emit('labels-updated', response.data.labels);
    } catch (error) {
        console.error('Error guardando etiquetas', error);
    } finally {
        saving.value = false;
    }
};

watch(() => props.phone, () => {
    if (props.phone) {
        loadCustomerLabels();
    }
}, { immediate: true });

const onOpenChange = (open: boolean) => {
    if (open && labels.value.length === 0) {
        loadLabels();
    }
};
</script>

<template>
    <DropdownMenu @update:open="onOpenChange">
        <DropdownMenuTrigger asChild>
            <Button variant="outline" size="sm" class="h-8 gap-1.5" :disabled="!phone">
                <Tag class="h-3.5 w-3.5" />
                <span>Etiquetas</span>
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent class="w-64 p-3" align="end">
            <div class="space-y-3">
                <h4 class="text-sm font-semibold">Asignar Etiquetas</h4>
                
                <div v-if="loadingLabels || loadingCustomer" class="flex justify-center py-4">
                    <Loader2 class="h-4 w-4 animate-spin text-muted-foreground" />
                </div>
                
                <div v-else-if="labels.length === 0" class="text-xs text-muted-foreground text-center py-2">
                    No hay etiquetas creadas. Ve a Configuración > Etiquetas para crear una.
                </div>
                
                <div v-else class="space-y-1">
                    <button
                        v-for="label in labels"
                        :key="label.id"
                        type="button"
                        class="flex w-full items-center justify-between rounded-md px-2 py-1.5 text-sm transition hover:bg-muted"
                        @click="toggleLabel(label.id)"
                        :disabled="saving"
                    >
                        <div class="flex items-center gap-2">
                            <span class="w-3 h-3 rounded-full border border-black/10" :style="{ backgroundColor: label.color }"></span>
                            <span>{{ label.name }}</span>
                        </div>
                        <Check v-if="customerLabels.includes(label.id)" class="h-4 w-4 text-emerald-600" />
                    </button>
                </div>
            </div>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
