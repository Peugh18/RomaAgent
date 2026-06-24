<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import CrmAlert from '@/components/crm/CrmAlert.vue';
import CrmAnimatedSection from '@/components/crm/CrmAnimatedSection.vue';
import CrmPageHero from '@/components/crm/CrmPageHero.vue';
import CrmPanel from '@/components/crm/CrmPanel.vue';
import CrmStickySaveBar from '@/components/crm/CrmStickySaveBar.vue';
import ConfigSectionHeader from '@/components/configuracion/ConfigSectionHeader.vue';
import { Head } from '@inertiajs/vue3';
import { ref, onMounted, provide, computed } from 'vue';
import { useConfiguracionEmpresa } from '@/composables/useConfiguracionEmpresa';
import PreviewPrompt from '@/components/Configuracion/PreviewPrompt.vue';
import EmpresaVendedorTab from '@/components/Configuracion/EmpresaVendedorTab.vue';
import MetodosPagoTab from '@/components/Configuracion/MetodosPagoTab.vue';
import PersonalidadIaTab from '@/components/Configuracion/PersonalidadIaTab.vue';
import FlujoVentasTab from '@/components/Configuracion/FlujoVentasTab.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Building2,
    MessageSquare,
    DollarSign,
    CreditCard,
    FileText,
    Trash2,
    Plus,
    Bot,
    Key,
    Sliders,
    Share2,
    Truck,
    AlertTriangle,
    Link2,
} from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Configuración de empresa', href: '/configuracion/empresa' },
];

const { 
    form, 
    loading,
    saving, 
    resetting,
    error, 
    success, 
    promptCompleto,
    promptSecciones,
    guardarConfiguracion, 
    resetearConfiguracion,
    agregarMetodoPago, 
    eliminarMetodoPago,
    cargarDatos,
    probarConexionIA,
} = useConfiguracionEmpresa();

const modalResetAbierto = ref(false);
const confirmacionReset = ref('');

const puedeConfirmarReset = computed(() => confirmacionReset.value.trim().toUpperCase() === 'RESETEAR');

const ejecutarReset = async () => {
    await resetearConfiguracion();
    if (!error.value) {
        modalResetAbierto.value = false;
        confirmacionReset.value = '';
    }
};

const probandoIA = ref(false);
const resultadoPruebaIA = ref<string | null>(null);
const errorPruebaIA = ref<string | null>(null);

const probarIA = async () => {
    probandoIA.value = true;
    resultadoPruebaIA.value = null;
    errorPruebaIA.value = null;

    try {
        resultadoPruebaIA.value = await probarConexionIA();
    } catch (err) {
        errorPruebaIA.value = err instanceof Error ? err.message : 'Error al probar IA';
    } finally {
        probandoIA.value = false;
    }
};

const subiendoLogo = ref(false);

const subirLogo = async (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (!target.files || target.files.length === 0) return;

    const file = target.files[0];
    const formData = new FormData();
    formData.append('logo', file);

    subiendoLogo.value = true;
    error.value = null;

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const response = await fetch('/api/company-settings/logo', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                Accept: 'application/json',
            },
            body: formData,
        });

        const data = await response.json();
        if (!response.ok) {
            throw new Error(data.message || 'Error al subir el logo');
        }

        form.logo_path = data.logo_path;
        success.value = 'Logo subido con éxito. Recuerda guardar los cambios.';
    } catch (err) {
        error.value = err instanceof Error ? err.message : 'Error al subir la imagen';
    } finally {
        subiendoLogo.value = false;
        target.value = '';
    }
};

provide('companyConfigForm', form);

const pestanaActiva = ref('empresa');

// Las funciones del modal de métodos de pago fueron movidas a MetodosPagoTab.vue

onMounted(() => {
    cargarDatos();
});


const actividades = [
    'Moda y Vestuario',
    'Restaurante',
    'Servicios Profesionales',
    'Tecnología',
    'Educación',
    'Salud y Bienestar',
    'Comercio Electrónico',
    'Otro',
];

const monedas = [
    { code: 'PEN', label: 'Soles (S/)' },
    { code: 'USD', label: 'Dólares ($)' },
    { code: 'EUR', label: 'Euros (€)' },
];

provide('monedas', monedas);

const heroStats = computed(() => {
    const stats = form.estadisticas as {
        completitud?: number;
        productos_activos?: number;
    } | undefined;

    if (!stats) {
        return [];
    }

    return [
        { label: 'Completitud', value: `${stats.completitud ?? 0}%` },
        { label: 'Productos', value: stats.productos_activos ?? 0 },
    ];
});
</script>

<template>
    <Head title="Configuración de Empresa" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div v-if="loading" class="crm-page flex min-h-[40vh] items-center justify-center">
            <p class="text-sm text-muted-foreground">Cargando configuración…</p>
        </div>
        <div v-else class="crm-page space-y-4 pb-20">
            <CrmPageHero
                title="Configuración de empresa"
                description="Datos de tu marca, pagos, IA y mensajes. La IA responde según esta configuración."
                :icon="Building2"
                variant="emerald"
                :stats="heroStats"
            />

            <CrmAlert v-if="error">{{ error }}</CrmAlert>
            <CrmAlert v-if="success" variant="success">{{ success }}</CrmAlert>

            <CrmAnimatedSection :delay="80">
            <!-- Layout Split: Formulario (Izquierda) + Preview (Derecha) -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- FORMULARIO (Izquierda - 2 columnas) -->
                <div class="lg:col-span-2 space-y-6">
            <Tabs v-model="pestanaActiva" class="w-full">
                <TabsList class="grid h-auto w-full grid-cols-3 gap-0.5 rounded-xl border border-border/50 bg-muted/30 p-1 sm:grid-cols-4">
                    <!-- 1. Datos Básicos -->
                    <TabsTrigger value="empresa" class="rounded-lg text-xs data-[state=active]:bg-background data-[state=active]:shadow-sm">
                        <Building2 class="h-4 w-4 mr-1 hidden sm:inline" />
                        <span class="hidden sm:inline">Empresa y Vendedor</span>
                        <span class="sm:hidden">Empresa</span>
                    </TabsTrigger>

                    <!-- 2. Personalidad IA -->
                    <TabsTrigger value="personalidad" class="rounded-lg text-xs data-[state=active]:bg-background data-[state=active]:shadow-sm">
                        <MessageSquare class="h-4 w-4 mr-1 hidden sm:inline" />
                        <span class="hidden sm:inline">Personalidad</span>
                        <span class="sm:hidden">Per</span>
                    </TabsTrigger>

                    <!-- 3. Flujo de Ventas -->
                    <TabsTrigger value="flujo" class="rounded-lg text-xs data-[state=active]:bg-background data-[state=active]:shadow-sm">
                        <Zap class="h-4 w-4 mr-1 hidden sm:inline" />
                        <span class="hidden sm:inline">Flujo</span>
                        <span class="sm:hidden">Flu</span>
                    </TabsTrigger>

                    <!-- 4. Métodos de Pago -->
                    <TabsTrigger value="pago" class="rounded-lg text-xs data-[state=active]:bg-background data-[state=active]:shadow-sm">
                        <CreditCard class="h-4 w-4 mr-1 hidden sm:inline" />
                        <span class="hidden sm:inline">Pago</span>
                        <span class="sm:hidden">Pag</span>
                    </TabsTrigger>
                </TabsList>

                <!-- TAB 1: DATOS DE EMPRESA Y VENDEDOR -->
                <TabsContent value="empresa" class="mt-6 space-y-6">
                    <EmpresaVendedorTab :subiendoLogo="subiendoLogo" @subirLogo="subirLogo" />
                </TabsContent>

                <!-- TAB 3: PERSONALIDAD DEL BOT -->
                <TabsContent value="personalidad" class="mt-6 space-y-6">
                    <PersonalidadIaTab />
                </TabsContent>

                <!-- TAB 4: MÉTODOS DE PAGO -->
                <TabsContent value="pago" class="mt-6 space-y-6">
                    <MetodosPagoTab />
                </TabsContent>

                <!-- TAB 5: FLUJO DE VENTAS -->
                <TabsContent value="flujo" class="mt-6 space-y-6">
                    <FlujoVentasTab />
                </TabsContent>

            </Tabs>

                    <CrmStickySaveBar
                        :saving="saving"
                        hint="Los cambios actualizan el prompt de la IA al guardar."
                        @save="guardarConfiguracion"
                    />

                    <div class="mt-8 overflow-hidden rounded-2xl border border-red-500/30">
                        <CrmPanel>
                        <ConfigSectionHeader
                            :icon="AlertTriangle"
                            title="Zona peligrosa"
                            description="Borra empresa, personalidad, pagos, flujo de ventas, API key de IA e historial de logs IA. No elimina productos, categorías ni conversaciones de WhatsApp."
                        />
                        <Button
                            variant="destructive"
                            class="gap-1 p-1"
                            :disabled="resetting"
                            @click="modalResetAbierto = true"
                        >
                            <Trash2 class="h-4 w-4" />
                            {{ resetting ? 'Restableciendo...' : 'Eliminar toda la configuración' }}
                        </Button>
                        </CrmPanel>
                    </div>

                    <Dialog v-model:open="modalResetAbierto">
                        <DialogContent class="p-2">
                            <DialogHeader>
                                <DialogTitle>¿Eliminar toda la configuración?</DialogTitle>
                                <DialogDescription>
                                    Esta acción no se puede deshacer. Escribe <strong>RESETEAR</strong> para confirmar.
                                </DialogDescription>
                            </DialogHeader>
                            <div class="space-y-3">
                                <Label for="confirm-reset">Confirmación</Label>
                                <Input
                                    id="confirm-reset"
                                    v-model="confirmacionReset"
                                    placeholder="Escribe RESETEAR"
                                    autocomplete="off"
                                />
                            </div>
                            <div class="flex gap-2">
                                <Button variant="outline" class="flex-1" @click="modalResetAbierto = false">
                                    Cancelar
                                </Button>
                                <Button
                                    variant="destructive"
                                    class="flex-1"
                                    :disabled="!puedeConfirmarReset || resetting"
                                    @click="ejecutarReset"
                                >
                                    {{ resetting ? 'Restableciendo...' : 'Sí, eliminar todo' }}
                                </Button>
                            </div>
                        </DialogContent>
                    </Dialog>
                </div>

                <!-- PREVIEW (Derecha - 1 columna) -->
                <div class="lg:col-span-1">
                    <PreviewPrompt
                        :prompt-completo="promptCompleto"
                        :prompt-secciones="promptSecciones"
                        :estadisticas="form.estadisticas || {}"
                        :probando="probandoIA"
                        :resultado-prueba="resultadoPruebaIA"
                        :error-prueba="errorPruebaIA"
                        @probar-ia="probarIA"
                    />
                </div>
            </div>
            </CrmAnimatedSection>
        </div>
    </AppLayout>
</template>
