<script setup lang="ts">
import { ref, watch } from 'vue';
import axios from 'axios';
import { Tag, Loader2, Check, Send } from 'lucide-vue-next';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { apiJson, getCsrfToken } from '@/composables/useApi';

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

// Dialog state for "send label message?"
const sendDialogOpen = ref(false);
const pendingLabelName = ref('');
const sendingLabelMessage = ref(false);

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
    
    const wasAdding = !customerLabels.value.includes(labelId);
    const labelObj = labels.value.find((l) => l.id === labelId);
    
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

        // If we just ADDED a label, ask if they want to send a message
        if (wasAdding && labelObj) {
            pendingLabelName.value = labelObj.name;
            sendDialogOpen.value = true;
        }
    } catch (error) {
        console.error('Error guardando etiquetas', error);
    } finally {
        saving.value = false;
    }
};

const sendLabelMessage = async () => {
    if (!props.phone || !pendingLabelName.value) return;
    sendingLabelMessage.value = true;
    try {
        await apiJson('/api/messages/send-system', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify({
                phone_number: props.phone,
                content: `*${pendingLabelName.value}*`,
            }),
        });
    } catch (error) {
        console.error('Error enviando mensaje de etiqueta', error);
    } finally {
        sendingLabelMessage.value = false;
        sendDialogOpen.value = false;
        pendingLabelName.value = '';
    }
};

const dismissDialog = () => {
    sendDialogOpen.value = false;
    pendingLabelName.value = '';
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

    <!-- Dialog: send label name as WhatsApp message? -->
    <Dialog v-model:open="sendDialogOpen">
        <DialogContent class="sm:max-w-sm">
            <DialogHeader>
                <DialogTitle class="text-base">Enviar etiqueta al chat</DialogTitle>
                <DialogDescription>
                    ¿Deseas enviar un mensaje con el nombre de esta etiqueta al chat? 
                    Esto te permitirá filtrar esta conversación desde WhatsApp.
                </DialogDescription>
            </DialogHeader>

            <div class="rounded-lg border bg-muted/30 p-3">
                <p class="text-sm font-medium text-center">*{{ pendingLabelName }}*</p>
                <p class="text-[10px] text-muted-foreground text-center mt-1">Se enviará como mensaje en negrita</p>
            </div>

            <DialogFooter class="gap-2 sm:gap-0">
                <Button variant="outline" size="sm" :disabled="sendingLabelMessage" @click="dismissDialog">
                    Solo asignar
                </Button>
                <Button size="sm" :disabled="sendingLabelMessage" @click="sendLabelMessage">
                    <Loader2 v-if="sendingLabelMessage" class="mr-1.5 h-3.5 w-3.5 animate-spin" />
                    <Send v-else class="mr-1.5 h-3.5 w-3.5" />
                    Sí, enviar
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
