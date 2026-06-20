<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label as UILabel } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Trash2, Pencil, Plus, Tag, Search, MessageSquare } from 'lucide-vue-next';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import CrmAnimatedSection from '@/components/crm/CrmAnimatedSection.vue';
import { useChat } from '@/composables/useChat';
import { formatChatTime } from '@/lib/chatFormatting';
import { getInitials } from '@/composables/useInitials';

interface Label {
    id: number;
    name: string;
    color: string;
    customers_count?: number;
}

const props = defineProps<{
    labels: Label[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Etiquetas',
        href: '/etiquetas',
    },
];

// Composable para obtener los chats
const { conversations, initialLoad } = useChat();

onMounted(() => {
    initialLoad();
});

const activeLabelId = ref<number | null>(null);

const activeLabel = computed(() => {
    if (!activeLabelId.value) return null;
    return props.labels.find(l => l.id === activeLabelId.value) || null;
});

const filteredConversations = computed(() => {
    if (!activeLabelId.value) return [];
    return conversations.value.filter(c => c.labels?.some(l => l.id === activeLabelId.value));
});

const form = ref({
    id: null as number | null,
    name: '',
    color: '#3b82f6',
});

const isEditing = ref(false);
const isSaving = ref(false);
const isModalOpen = ref(false);
const searchQuery = ref('');

const filteredLabels = computed(() => {
    if (!searchQuery.value.trim()) return props.labels;
    const query = searchQuery.value.toLowerCase();
    return props.labels.filter(l => l.name.toLowerCase().includes(query));
});

const openCreateModal = () => {
    form.value = { id: null, name: '', color: '#3b82f6' };
    isEditing.value = false;
    isModalOpen.value = true;
};

const editLabel = (label: Label) => {
    form.value = { id: label.id, name: label.name, color: label.color };
    isEditing.value = true;
    isModalOpen.value = true;
};

const submit = async () => {
    if (!form.value.name.trim()) return;
    
    isSaving.value = true;
    try {
        if (isEditing.value && form.value.id) {
            await axios.put(`/api/labels/${form.value.id}`, form.value);
        } else {
            await axios.post('/api/labels', form.value);
        }
        router.reload({ only: ['labels'] });
        isModalOpen.value = false;
    } catch (error) {
        console.error('Error guardando etiqueta:', error);
    } finally {
        isSaving.value = false;
    }
};

const deleteLabel = async (id: number) => {
    if (!confirm('¿Estás seguro de eliminar esta etiqueta? Se quitará de todos los chats que la tengan.')) return;
    
    try {
        await axios.delete(`/api/labels/${id}`);
        router.reload({ only: ['labels'] });
        if (activeLabelId.value === id) {
            activeLabelId.value = null;
        }
    } catch (error) {
        console.error('Error eliminando etiqueta:', error);
    }
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Etiquetas de WhatsApp" />

        <div class="crm-page !gap-4 pb-4 pt-4 h-full">
            <CrmAnimatedSection :delay="60" class="h-full">
                <div class="flex h-[calc(100vh-8rem)] min-h-[32rem] overflow-hidden lg:rounded-2xl lg:border lg:border-border/50 lg:shadow-md bg-card">
                    
                    <!-- Sidebar: Lista de Etiquetas -->
                    <aside class="flex w-full max-w-[320px] flex-col border-r border-border/60 bg-background/50">
                        <div class="flex items-center justify-between border-b p-4 h-16 shrink-0">
                            <h2 class="text-base font-semibold text-foreground">Etiquetas</h2>
                            <Button @click="openCreateModal" size="sm" variant="ghost" class="h-8 w-8 p-0 hover:bg-emerald-100 hover:text-emerald-700 dark:hover:bg-emerald-900/40">
                                <Plus class="h-4 w-4" />
                            </Button>
                        </div>
                        
                        <div class="p-3 border-b">
                            <div class="relative">
                                <Search class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                                <Input v-model="searchQuery" placeholder="Buscar etiqueta..." class="pl-9 bg-card h-9" />
                            </div>
                        </div>

                        <div class="flex-1 overflow-y-auto p-2 space-y-1">
                            <div v-if="filteredLabels.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                                No se encontraron etiquetas.
                            </div>
                            
                            <div 
                                v-for="label in filteredLabels" 
                                :key="label.id" 
                                @click="activeLabelId = label.id"
                                class="flex items-center justify-between p-2.5 rounded-lg transition cursor-pointer group"
                                :class="activeLabelId === label.id ? 'bg-muted shadow-sm ring-1 ring-border/50' : 'hover:bg-muted/50'"
                            >
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="w-4 h-4 rounded-full shrink-0 shadow-sm border border-black/10 flex items-center justify-center" :style="{ backgroundColor: label.color }">
                                        <div class="w-1.5 h-1.5 bg-white/40 rounded-full"></div>
                                    </div>
                                    <span class="font-medium text-sm truncate" :class="{'text-foreground': activeLabelId === label.id, 'text-muted-foreground group-hover:text-foreground': activeLabelId !== label.id}">
                                        {{ label.name }}
                                    </span>
                                </div>
                                
                                <div class="flex items-center gap-1">
                                    <span class="text-xs font-medium text-muted-foreground bg-background/80 px-1.5 py-0.5 rounded-md" v-if="activeLabelId !== label.id">
                                        {{ label.customers_count || 0 }}
                                    </span>
                                    <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity" v-if="activeLabelId === label.id">
                                        <button class="p-1 hover:text-foreground text-muted-foreground transition-colors" @click.stop="editLabel(label)" title="Editar">
                                            <Pencil class="w-3.5 h-3.5" />
                                        </button>
                                        <button class="p-1 hover:text-destructive text-muted-foreground transition-colors" @click.stop="deleteLabel(label.id)" title="Eliminar">
                                            <Trash2 class="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </aside>

                    <!-- Main Pane: Lista de Chats -->
                    <main class="flex-1 bg-[#efeae2] dark:bg-muted/20 flex flex-col min-w-0">
                        <div v-if="!activeLabelId" class="flex-1 flex flex-col items-center justify-center text-muted-foreground/60">
                            <div class="bg-card p-6 rounded-2xl shadow-sm border mb-4">
                                <Tag class="w-12 h-12 text-muted-foreground/40" />
                            </div>
                            <h3 class="text-lg font-medium text-foreground mb-1">Ninguna etiqueta seleccionada</h3>
                            <p class="text-sm">Selecciona una etiqueta a la izquierda para ver sus conversaciones.</p>
                        </div>
                        
                        <div v-else class="flex-1 flex flex-col h-full">
                            <!-- Header de la etiqueta -->
                            <div class="h-16 shrink-0 border-b bg-card flex items-center justify-between px-6 shadow-sm z-10">
                                <div class="flex items-center gap-3">
                                    <div class="w-5 h-5 rounded-full shadow-sm border border-black/10 flex items-center justify-center" :style="{ backgroundColor: activeLabel?.color }">
                                        <div class="w-2 h-2 bg-white/40 rounded-full"></div>
                                    </div>
                                    <h3 class="font-semibold text-lg text-foreground">{{ activeLabel?.name }}</h3>
                                    <span class="text-xs font-medium text-muted-foreground bg-muted px-2.5 py-1 rounded-full border">
                                        {{ filteredConversations.length }} chats
                                    </span>
                                </div>
                                <Button size="sm" variant="outline" @click="router.visit(`/chat?label=${activeLabelId}`)" class="gap-2">
                                    <MessageSquare class="w-4 h-4" />
                                    <span>Ir al Inbox</span>
                                </Button>
                            </div>
                            
                            <!-- Grilla de conversaciones -->
                            <div class="flex-1 overflow-y-auto p-6 bg-[url('data:image/svg+xml,%3Csvg width=%2260%22 height=%2260%22 viewBox=%220 0 60 60%22 xmlns=%22http://www.w3.org/2000/svg%22%3E%3Cg fill=%22none%22 fill-rule=%22evenodd%22%3E%3Cg fill=%22%23d4cdc4%22 fill-opacity=%220.15%22%3E%3Cpath d=%22M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z%22/%3E%3C/g%3E%3C/g%3E%3C/svg%3E')] dark:bg-none">
                                <div v-if="filteredConversations.length === 0" class="flex flex-col items-center justify-center h-full text-muted-foreground opacity-60">
                                    <MessageSquare class="w-12 h-12 mb-3" />
                                    <p>No hay conversaciones con esta etiqueta.</p>
                                </div>
                                
                                <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 auto-rows-max">
                                    <div 
                                        v-for="chat in filteredConversations" 
                                        :key="chat.phone"
                                        @click="router.visit('/chat?phone=' + chat.phone)"
                                        class="bg-card/90 backdrop-blur-sm border rounded-xl p-4 cursor-pointer hover:border-emerald-500/50 hover:shadow-md transition-all group flex flex-col h-[130px]"
                                    >
                                        <div class="flex items-center gap-3 mb-3 shrink-0">
                                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-sm font-semibold text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-300">
                                                {{ getInitials(chat.name || chat.phone) }}
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <div class="font-semibold text-sm truncate text-foreground group-hover:text-emerald-600 dark:group-hover:text-emerald-400 transition-colors">
                                                    {{ chat.name || chat.phone }}
                                                </div>
                                                <div class="text-[11px] text-muted-foreground">{{ formatChatTime(chat.last_at) }}</div>
                                            </div>
                                        </div>
                                        
                                        <p class="text-sm text-muted-foreground line-clamp-2 mt-auto">
                                            {{ chat.last_message }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </main>
                </div>
            </CrmAnimatedSection>
        </div>

        <!-- Modal de Creación/Edición -->
        <Dialog :open="isModalOpen" @update:open="isModalOpen = $event">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{{ isEditing ? 'Editar Etiqueta' : 'Nueva Etiqueta' }}</DialogTitle>
                </DialogHeader>
                
                <form @submit.prevent="submit" class="space-y-4 py-4">
                    <div class="grid gap-2">
                        <UILabel for="name">Nombre de la Etiqueta</UILabel>
                        <Input id="name" v-model="form.name" required placeholder="Ej: Mayorista" autofocus />
                    </div>

                    <div class="grid gap-2">
                        <UILabel for="color">Color</UILabel>
                        <div class="flex gap-2 items-center">
                            <Input id="color" type="color" class="w-14 h-10 p-1 cursor-pointer rounded-md" v-model="form.color" />
                            <span class="text-sm text-muted-foreground font-mono uppercase">{{ form.color }}</span>
                        </div>
                    </div>

                    <DialogFooter class="pt-4">
                        <Button type="button" variant="outline" @click="isModalOpen = false" :disabled="isSaving">
                            Cancelar
                        </Button>
                        <Button type="submit" :disabled="isSaving || !form.name.trim()">
                            <span v-if="isSaving">Guardando...</span>
                            <span v-else>{{ isEditing ? 'Guardar Cambios' : 'Crear Etiqueta' }}</span>
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

    </AppLayout>
</template>
