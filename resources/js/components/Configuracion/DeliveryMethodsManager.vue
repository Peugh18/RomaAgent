<script setup lang="ts">
import { ref, onMounted, nextTick } from 'vue';
import axios from 'axios';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { Loader2, Plus, Trash2, Save, CheckCircle2 } from 'lucide-vue-next';

interface DeliveryMethodField {
    id?: number;
    name: string;
    is_required: boolean;
}

interface DeliveryMethod {
    id?: number;
    name: string;
    is_active: boolean;
    fields: DeliveryMethodField[];
}

const methods = ref<DeliveryMethod[]>([]);
const activeTab = ref('');
const loading = ref(true);
const saving = ref(false);
const showSuccess = ref(false);

const isCreating = ref(false);
const newMethodName = ref('');

const fetchMethods = async () => {
    loading.value = true;
    try {
        const { data } = await axios.get('/api/delivery-methods');
        methods.value = data;
        if (methods.value.length > 0 && !activeTab.value) {
            activeTab.value = methods.value[0].name;
        }
    } catch (e) {
        console.error(e);
    } finally {
        loading.value = false;
    }
};

const confirmCreate = () => {
    const name = newMethodName.value.trim();
    if (!name) return;
    
    if (methods.value.find(m => m.name.toLowerCase() === name.toLowerCase())) {
        alert('Ya existe un método con ese nombre.');
        return;
    }

    methods.value.push({
        name: name,
        is_active: true,
        fields: []
    });
    
    activeTab.value = name;
    isCreating.value = false;
    newMethodName.value = '';
};

const removeMethod = (index: number) => {
    if (!confirm('¿Estás seguro de eliminar este método de envío?')) return;
    
    if (methods.value[index].id) {
        deleteMethodApi(methods.value[index].id!, index);
    } else {
        methods.value.splice(index, 1);
        if (methods.value.length > 0) activeTab.value = methods.value[0].name;
    }
};

const deleteMethodApi = async (id: number, index: number) => {
    try {
        await axios.delete(`/api/delivery-methods/${id}`);
        methods.value.splice(index, 1);
        if (methods.value.length > 0) activeTab.value = methods.value[0].name;
    } catch (e) {
        console.error(e);
        alert('Error al eliminar');
    }
};

const addField = (method: DeliveryMethod) => {
    method.fields.push({
        name: '',
        is_required: true
    });
};

const removeField = (method: DeliveryMethod, fIdx: number) => {
    method.fields.splice(fIdx, 1);
};

const saveMethods = async () => {
    saving.value = true;
    showSuccess.value = false;
    try {
        for (const method of methods.value) {
            // Filtrar campos vacíos antes de guardar
            method.fields = method.fields.filter(f => f.name.trim() !== '');
            
            if (method.id) {
                await axios.put(`/api/delivery-methods/${method.id}`, method);
            } else {
                await axios.post(`/api/delivery-methods`, method);
            }
        }
        await fetchMethods();
        showSuccess.value = true;
        setTimeout(() => { showSuccess.value = false; }, 3000);
    } catch (e) {
        console.error(e);
        alert('Ocurrió un error al guardar los métodos de envío.');
    } finally {
        saving.value = false;
    }
};

onMounted(() => {
    fetchMethods();
});
</script>

<template>
    <Card class="border-blue-200 dark:border-blue-900 shadow-sm relative overflow-hidden">
        <div class="absolute top-0 left-0 w-1 h-full bg-blue-500"></div>
        <CardHeader class="pb-3">
            <div class="flex justify-between items-start">
                <div>
                    <CardTitle class="text-lg">Métodos de Envío Dinámicos</CardTitle>
                    <CardDescription>
                        Crea opciones de envío y define qué datos le pedirá la IA al cliente para cada uno.
                    </CardDescription>
                </div>
                <Button variant="outline" size="sm" @click="isCreating = true" v-if="!isCreating" class="flex gap-2">
                    <Plus class="h-4 w-4" />
                    Nuevo Método
                </Button>
            </div>
        </CardHeader>

        <CardContent>
            <div v-if="isCreating" class="mb-4 p-4 border rounded-md bg-muted/30 flex flex-col sm:flex-row gap-3 items-center animate-in fade-in slide-in-from-top-2">
                <Label class="whitespace-nowrap">Nombre:</Label>
                <Input v-model="newMethodName" placeholder="Ej: Olva Courier" @keyup.enter="confirmCreate" />
                <div class="flex gap-2 w-full sm:w-auto">
                    <Button @click="confirmCreate" size="sm" class="flex-1 sm:flex-none">Crear</Button>
                    <Button @click="isCreating = false" variant="ghost" size="sm" class="flex-1 sm:flex-none">Cancelar</Button>
                </div>
            </div>

            <div v-if="loading" class="flex justify-center py-8">
                <Loader2 class="h-6 w-6 animate-spin text-muted-foreground" />
            </div>
            <div v-else-if="methods.length === 0" class="text-center py-8 text-muted-foreground border rounded-md border-dashed">
                No hay métodos de envío. ¡Crea uno nuevo!
            </div>
            <div v-else>
                <Tabs v-model="activeTab" class="w-full">
                    <TabsList class="flex flex-wrap h-auto w-full justify-start gap-1 p-1 bg-muted/50">
                        <TabsTrigger 
                            v-for="method in methods" 
                            :key="method.name" 
                            :value="method.name"
                            class="px-4 py-2"
                        >
                            {{ method.name }}
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent 
                        v-for="(method, index) in methods" 
                        :key="method.name" 
                        :value="method.name" 
                        class="space-y-4 pt-4 border rounded-b-md rounded-tr-md p-4 mt-0 bg-card"
                    >
                        <div class="flex justify-between items-center mb-2">
                            <Label class="text-sm font-semibold text-primary">Campos a solicitar</Label>
                            <Button variant="ghost" size="sm" class="text-destructive hover:text-destructive hover:bg-destructive/10" @click="removeMethod(index)">
                                <Trash2 class="h-4 w-4 mr-2" /> Eliminar método
                            </Button>
                        </div>
                        
                        <div class="space-y-3">
                            <div v-for="(field, fIdx) in method.fields" :key="fIdx" class="flex items-center gap-3">
                                <Input v-model="field.name" placeholder="Nombre del dato (Ej: DNI, Dirección)" class="flex-1" />
                                <label class="flex items-center gap-2 cursor-pointer whitespace-nowrap text-sm bg-muted/30 dark:bg-muted/10 p-2 px-3 rounded-md border border-border hover:bg-muted/50 transition-colors">
                                    <Checkbox :checked="field.is_required" @update:checked="field.is_required = $event" />
                                    <span>Obligatorio</span>
                                </label>
                                <Button variant="ghost" size="icon" @click="removeField(method, fIdx)" class="text-muted-foreground hover:text-destructive shrink-0">
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </div>

                            <Button variant="outline" size="sm" @click="addField(method)" class="w-full border-dashed mt-2">
                                <Plus class="h-4 w-4 mr-2" /> Añadir otro dato
                            </Button>
                        </div>

                        <p class="text-xs text-muted-foreground mt-4">
                            La IA solicitará estos datos cuando el cliente elija <strong>{{ method.name }}</strong>. Si marcas "Obligatorio", no le dejará avanzar sin darlos.
                        </p>
                    </TabsContent>
                </Tabs>
            </div>
        </CardContent>
        <div class="bg-muted/30 flex justify-end gap-3 p-4 border-t">
            <span v-if="showSuccess" class="flex items-center text-sm text-green-600 dark:text-green-400 gap-1 animate-in fade-in">
                <CheckCircle2 class="h-4 w-4" /> Guardado
            </span>
            <Button @click="saveMethods" :disabled="loading || saving || methods.length === 0">
                <Loader2 v-if="saving" class="h-4 w-4 mr-2 animate-spin" />
                <Save v-else class="h-4 w-4 mr-2" />
                Guardar Métodos de Envío
            </Button>
        </div>
    </Card>
</template>
