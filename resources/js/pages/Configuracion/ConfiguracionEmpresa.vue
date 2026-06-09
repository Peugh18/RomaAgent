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
import FlujoVentasTab from '@/components/Configuracion/FlujoVentasTab.vue';
import EntregasTab from '@/components/Configuracion/EntregasTab.vue';
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
    Smartphone,
    Landmark,
    Wallet,
    Banknote,
    ArrowLeftRight,
    Globe,
    QrCode,
    ImageIcon,
    Zap,
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

provide('companyConfigForm', form);

const pestanaActiva = ref('empresa');

// Modal de métodos de pago
const modalMetodosAbierto = ref(false);
const pasoModal = ref<'seleccion' | 'formulario'>('seleccion');
const metodoSeleccionado = ref<{
    nombre: string;
    icono: string;
    color: string;
    tipo: string;
} | null>(null);

const nuevoMetodoForm = ref({
    nombre: '',
    destinatario: '',
    numero_cuenta: '',
    descripcion: '',
    icono: '',
    tipo: '',
    imagen_url: '',
});

// Preview de imagen QR
const previewImagenQr = ref('');
const inputQrFile = ref<HTMLInputElement | null>(null);
const numeroCuentaValido = ref(false);

const validarNumeroCuenta = () => {
    const numero = nuevoMetodoForm.value.numero_cuenta.replace(/\D/g, '');
    nuevoMetodoForm.value.numero_cuenta = numero;
    // Validación para celular peruano: 9 dígitos, empieza con 9
    numeroCuentaValido.value = /^9\d{8}$/.test(numero);
};

const manejarArchivoQr = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (!file) return;

    if (file.size > 2 * 1024 * 1024) {
        alert('La imagen no debe superar los 2MB');
        return;
    }

    const reader = new FileReader();
    reader.onload = (e) => {
        const result = e.target?.result as string;
        previewImagenQr.value = result;
        nuevoMetodoForm.value.imagen_url = result;
    };
    reader.readAsDataURL(file);
};

const METODOS_PREDEFINIDOS = [
    {
        categoria: 'Pagos Virtuales',
        metodos: [
            { nombre: 'Yape', icono: 'Smartphone', color: 'bg-purple-600 text-white', tipo: 'virtual' },
            { nombre: 'Plin', icono: 'Smartphone', color: 'bg-cyan-500 text-white', tipo: 'virtual' },
            { nombre: 'Tunki', icono: 'Smartphone', color: 'bg-pink-500 text-white', tipo: 'virtual' },
            { nombre: 'Paypal', icono: 'Globe', color: 'bg-blue-700 text-white', tipo: 'virtual' },
        ],
    },
    {
        categoria: 'Depósitos Bancarios',
        metodos: [
            { nombre: 'Banco de la Nación', icono: 'Landmark', color: 'bg-red-600 text-white', tipo: 'banco' },
            { nombre: 'BCP', icono: 'Landmark', color: 'bg-blue-600 text-white', tipo: 'banco' },
            { nombre: 'Interbank', icono: 'Landmark', color: 'bg-emerald-600 text-white', tipo: 'banco' },
            { nombre: 'Transferencia', icono: 'ArrowLeftRight', color: 'bg-slate-600 text-white', tipo: 'banco' },
        ],
    },
    {
        categoria: 'Otros',
        metodos: [
            { nombre: 'Tarjeta', icono: 'CreditCard', color: 'bg-indigo-600 text-white', tipo: 'otro' },
            { nombre: 'Efectivo', icono: 'Banknote', color: 'bg-green-600 text-white', tipo: 'otro' },
            { nombre: 'Otro', icono: 'Wallet', color: 'bg-gray-600 text-white', tipo: 'otro' },
        ],
    },
];

const abrirModalMetodos = () => {
    pasoModal.value = 'seleccion';
    metodoSeleccionado.value = null;
    previewImagenQr.value = '';
    if (inputQrFile.value) inputQrFile.value.value = '';
    nuevoMetodoForm.value = {
        nombre: '',
        destinatario: '',
        numero_cuenta: '',
        descripcion: '',
        icono: '',
        tipo: '',
        imagen_url: '',
    };
    modalMetodosAbierto.value = true;
};

const seleccionarMetodo = (metodo: typeof METODOS_PREDEFINIDOS[0]['metodos'][0]) => {
    metodoSeleccionado.value = metodo;
    nuevoMetodoForm.value.nombre = metodo.nombre;
    nuevoMetodoForm.value.icono = metodo.icono;
    nuevoMetodoForm.value.tipo = metodo.tipo;
    pasoModal.value = 'formulario';
};

const guardarMetodo = () => {
    if (!nuevoMetodoForm.value.nombre || !nuevoMetodoForm.value.destinatario) {
        return;
    }
    if (!numeroCuentaValido.value) {
        alert('Por favor ingresa un número de celular válido de 9 dígitos');
        return;
    }
    agregarMetodoPago({
        nombre: nuevoMetodoForm.value.nombre,
        descripcion: nuevoMetodoForm.value.descripcion || `${nuevoMetodoForm.value.nombre} - ${nuevoMetodoForm.value.destinatario}`,
        destinatario: nuevoMetodoForm.value.destinatario,
        numero_cuenta: nuevoMetodoForm.value.numero_cuenta,
        icono: nuevoMetodoForm.value.icono,
        tipo: nuevoMetodoForm.value.tipo,
        imagen_url: nuevoMetodoForm.value.imagen_url,
    });
    modalMetodosAbierto.value = false;
    pasoModal.value = 'seleccion';
    metodoSeleccionado.value = null;
};

const volverASeleccion = () => {
    pasoModal.value = 'seleccion';
    metodoSeleccionado.value = null;
};

const obtenerIconoComponente = (nombreIcono: string) => {
    const mapa: Record<string, any> = {
        Smartphone,
        Landmark,
        Wallet,
        Banknote,
        ArrowLeftRight,
        Globe,
        CreditCard,
    };
    return mapa[nombreIcono] || CreditCard;
};

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
        <div v-else class="crm-page space-y-6 pb-20">
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
                <TabsList class="grid h-auto w-full grid-cols-3 gap-1 rounded-xl border border-border/50 bg-muted/30 p-1 sm:grid-cols-6">
                    <!-- 1. Datos Básicos -->
                    <TabsTrigger value="empresa" class="rounded-lg text-xs data-[state=active]:bg-background data-[state=active]:shadow-sm">
                        <Building2 class="h-4 w-4 mr-1 hidden sm:inline" />
                        <span class="hidden sm:inline">Empresa</span>
                        <span class="sm:hidden">Emp</span>
                    </TabsTrigger>

                    <!-- 2. Contacto y Redes -->
                    <TabsTrigger value="contacto" class="rounded-lg text-xs data-[state=active]:bg-background data-[state=active]:shadow-sm">
                        <Smartphone class="h-4 w-4 mr-1 hidden sm:inline" />
                        <span class="hidden sm:inline">Contacto</span>
                        <span class="sm:hidden">Con</span>
                    </TabsTrigger>

                    <!-- 3. Personalidad IA -->
                    <TabsTrigger value="personalidad" class="rounded-lg text-xs data-[state=active]:bg-background data-[state=active]:shadow-sm">
                        <MessageSquare class="h-4 w-4 mr-1 hidden sm:inline" />
                        <span class="hidden sm:inline">Personalidad</span>
                        <span class="sm:hidden">Per</span>
                    </TabsTrigger>

                    <!-- 4. Flujo de Ventas -->
                    <TabsTrigger value="flujo" class="rounded-lg text-xs data-[state=active]:bg-background data-[state=active]:shadow-sm">
                        <Zap class="h-4 w-4 mr-1 hidden sm:inline" />
                        <span class="hidden sm:inline">Flujo</span>
                        <span class="sm:hidden">Flu</span>
                    </TabsTrigger>

                    <!-- 5. Métodos de Pago -->
                    <TabsTrigger value="pago" class="rounded-lg text-xs data-[state=active]:bg-background data-[state=active]:shadow-sm">
                        <CreditCard class="h-4 w-4 mr-1 hidden sm:inline" />
                        <span class="hidden sm:inline">Pago</span>
                        <span class="sm:hidden">Pag</span>
                    </TabsTrigger>

                    <!-- 6. Entregas -->
                    <TabsTrigger value="entregas" class="rounded-lg text-xs data-[state=active]:bg-background data-[state=active]:shadow-sm">
                        <Truck class="h-4 w-4 mr-1 hidden sm:inline" />
                        <span class="hidden sm:inline">Entregas</span>
                        <span class="sm:hidden">Ent</span>
                    </TabsTrigger>
                </TabsList>

                <!-- TAB 1: DATOS DE EMPRESA -->
                <TabsContent value="empresa" class="mt-6 space-y-6">
                    <CrmPanel>
                        <ConfigSectionHeader
                            :icon="Building2"
                            title="Datos de la Empresa"
                            description="Información básica de tu negocio"
                        />
                        <div class="space-y-4">
                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="space-y-2">
                                    <Label for="company_name">Nombre de la Empresa *</Label>
                                    <Input id="company_name" v-model="form.company_name" placeholder="Tu Tienda" />
                                </div>
                                <div class="space-y-2">
                                    <Label for="ruc">RUC (Opcional)</Label>
                                    <Input id="ruc" v-model="form.ruc" placeholder="12345678901" />
                                </div>
                                <div class="space-y-2">
                                    <Label for="razon_social">Razón Social (Opcional)</Label>
                                    <Input id="razon_social" v-model="form.razon_social" placeholder="Tu Empresa S.A.C." />
                                </div>
                                <div class="space-y-2">
                                    <Label for="actividad">Actividad Económica *</Label>
                                    <select
                                        id="actividad"
                                        v-model="form.actividad_economica"
                                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                    >
                                        <option value="">-- Selecciona una actividad --</option>
                                        <option v-for="actividad in actividades" :key="actividad" :value="actividad">
                                            {{ actividad }}
                                        </option>
                                    </select>
                                </div>
                            </div>

                            <!-- Logo -->
                            <div class="space-y-2">
                                <Label for="logo">Logo de la Empresa</Label>
                                <div class="flex items-center gap-4">
                                    <div v-if="form.logo_path" class="h-20 w-20 overflow-hidden rounded-xl border border-border/60">
                                        <img :src="form.logo_path" :alt="form.company_name" class="h-full w-full object-cover" />
                                    </div>
                                    <div class="flex-1">
                                        <Input id="logo" type="file" accept="image/*" class="cursor-pointer" />
                                        <p class="text-xs text-muted-foreground mt-1">PNG, JPG o GIF. Máximo 2MB</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CrmPanel>
                </TabsContent>

                <!-- TAB 2: CONTACTO Y REDES SOCIALES -->
                <TabsContent value="contacto" class="mt-6 space-y-6">
                    <CrmPanel>
                        <ConfigSectionHeader
                            :icon="Smartphone"
                            title="Información de Contacto"
                            description="Cómo pueden comunicarse contigo"
                        />
                        <div class="space-y-4">
                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="space-y-2">
                                    <Label for="celular">Celular *</Label>
                                    <Input id="celular" v-model="form.celular" placeholder="959696911" />
                                </div>
                                <div class="space-y-2">
                                    <Label for="email">Email *</Label>
                                    <Input id="email" v-model="form.email" type="email" placeholder="contacto@empresa.com" />
                                </div>
                            </div>
                            <div class="space-y-2">
                                <Label for="website">Sitio Web (Opcional)</Label>
                                <Input id="website" v-model="form.website" placeholder="https://miempresa.com" />
                            </div>
                        </div>
                    </CrmPanel>

                    <CrmPanel>
                        <ConfigSectionHeader
                            :icon="Share2"
                            title="Redes Sociales"
                            description="Tus perfiles en redes sociales (opcional)"
                        />
                        <div class="grid gap-4 md:grid-cols-3">
                            <div class="space-y-2">
                                <Label for="instagram">Instagram</Label>
                                <Input id="instagram" v-model="form.social_networks.instagram" placeholder="@tuempresa" />
                            </div>
                            <div class="space-y-2">
                                <Label for="facebook">Facebook</Label>
                                <Input id="facebook" v-model="form.social_networks.facebook" placeholder="facebook.com/tuempresa" />
                            </div>
                            <div class="space-y-2">
                                <Label for="tiktok">TikTok</Label>
                                <Input id="tiktok" v-model="form.social_networks.tiktok" placeholder="@tuempresa" />
                            </div>
                        </div>
                    </CrmPanel>
                </TabsContent>

                <!-- TAB 3: PERSONALIDAD DEL BOT -->
                <TabsContent value="personalidad" class="mt-6 space-y-6">
                    <CrmPanel>
                        <ConfigSectionHeader
                            :icon="MessageSquare"
                            title="Identidad y personalidad"
                            description="Quién es el bot, cómo habla y cómo trata al cliente. Va directo al prompt de la IA."
                        />
                        <div class="space-y-4">
                            <div class="space-y-2">
                                <Label for="personalidad_bot">Personalidad del bot *</Label>
                                <Textarea
                                    id="personalidad_bot"
                                    v-model="form.personalidad_bot"
                                    placeholder="Ej: Eres una asesora de ventas de moda femenina. Tu trato es amable, cercano, elegante y profesional. Hablas en femenino, de forma natural. Te diriges a la clienta usando tú y palabras cálidas como hermosa o bella. Nunca menciones que eres bot o IA."
                                    :rows="8"
                                />
                                <p class="text-xs text-muted-foreground">
                                    Sé específico: rol, trato, vocabulario, género gramatical, palabras que sí/no usar.
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label for="respuesta_si_es_bot">Si preguntan si eres bot (Opcional)</Label>
                                <Textarea
                                    id="respuesta_si_es_bot"
                                    v-model="form.respuesta_si_es_bot"
                                    placeholder="Ej: Soy asesora de Roma Store, bella. Te estoy ayudando por aquí para que sea más rápido, pero si prefieres, me avisas y te derivo con alguien más del equipo."
                                    :rows="3"
                                />
                            </div>
                        </div>
                    </CrmPanel>

                    <CrmPanel>
                        <ConfigSectionHeader
                            :icon="Bot"
                            title="Agente de ventas IA"
                            description="Configura Gemini para respuestas automáticas en WhatsApp"
                        />
                        <div class="space-y-6">
                            <div class="flex items-start gap-3">
                                <Checkbox
                                    id="agente-activado"
                                    v-model:checked="form.configuracion_agente!.agente_ia_activado"
                                />
                                <div class="space-y-0.5">
                                    <Label for="agente-activado">Activar Agente IA</Label>
                                    <p class="text-xs text-muted-foreground">Responde automáticamente mensajes entrantes.</p>
                                </div>
                            </div>

                            <div v-if="form.configuracion_agente?.agente_ia_activado" class="space-y-4 border-t pt-4">
                                <div class="space-y-2">
                                    <Label for="modelo-ia">Modelo</Label>
                                    <select
                                        id="modelo-ia"
                                        v-model="form.configuracion_agente!.agente_ia_modelo"
                                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    >
                                        <option
                                            v-for="(label, value) in form.configuracion_agente?.modelos_disponibles || {}"
                                            :key="value"
                                            :value="value"
                                        >
                                            {{ label }}
                                        </option>
                                    </select>
                                </div>

                                <div class="space-y-2">
                                    <Label for="api-key" class="flex items-center gap-2">
                                        <Key class="h-4 w-4" />
                                        API Key (Google AI Studio)
                                    </Label>
                                    <Input
                                        id="api-key"
                                        v-model="form.configuracion_agente!.agente_ia_api_key"
                                        type="password"
                                        placeholder="AIzaSy..."
                                        autocomplete="off"
                                    />
                                    <p v-if="form.configuracion_agente?.api_key_configurada" class="text-xs text-emerald-600">
                                        API Key configurada. Deja en blanco para mantener la actual.
                                    </p>
                                </div>

                                <div class="space-y-2">
                                    <Label for="temperatura" class="flex items-center gap-2">
                                        <Sliders class="h-4 w-4" />
                                        Creatividad: {{ form.configuracion_agente!.agente_ia_temperatura }}
                                    </Label>
                                    <input
                                        id="temperatura"
                                        v-model.number="form.configuracion_agente!.agente_ia_temperatura"
                                        type="range"
                                        min="0"
                                        max="1"
                                        step="0.1"
                                        class="h-2 w-full"
                                    />
                                </div>
                            </div>
                        </div>
                    </CrmPanel>
                </TabsContent>

                <!-- TAB 4: MÉTODOS DE PAGO -->
                <TabsContent value="pago" class="mt-6 space-y-6">
                    <CrmPanel>
                        <ConfigSectionHeader
                            :icon="DollarSign"
                            title="Moneda"
                            description="Moneda de operación de la tienda"
                        />
                        <div>
                            <div class="space-y-2">
                                <Label for="moneda">Moneda de Operación</Label>
                                <select
                                    id="moneda"
                                    v-model="form.moneda"
                                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <option v-for="moneda in monedas" :key="moneda.code" :value="moneda.code">
                                        {{ moneda.label }}
                                    </option>
                                </select>
                            </div>
                        </div>
                    </CrmPanel>

                    <CrmPanel>
                        <ConfigSectionHeader
                            :icon="CreditCard"
                            title="Métodos de pago"
                            description="Configura las formas de pago aceptadas"
                        >
                            <template #actions>
                                <Button @click="abrirModalMetodos" variant="outline" size="sm" class="gap-1">
                                    <Plus class="h-4 w-4" />
                                    Agregar
                                </Button>
                            </template>
                        </ConfigSectionHeader>
                        <div class="space-y-4">
                            <!-- Lista de métodos agregados -->
                            <div v-if="form.metodos_pago && form.metodos_pago.length > 0" class="grid gap-3">
                                <div
                                    v-for="(metodo, index) in form.metodos_pago"
                                    :key="index"
                                    class="flex items-center gap-4 rounded-lg border p-4"
                                >
                                    <!-- Icono / QR placeholder -->
                                    <div class="flex flex-col items-center gap-1">
                                        <div
                                            v-if="metodo.imagen_url"
                                            class="h-16 w-16 rounded-lg border bg-cover bg-center"
                                            :style="{ backgroundImage: `url(${metodo.imagen_url})` }"
                                        />
                                        <div
                                            v-else
                                            class="flex h-16 w-16 items-center justify-center rounded-lg border bg-muted"
                                        >
                                            <QrCode class="h-6 w-6 text-muted-foreground" />
                                        </div>
                                        <span class="text-xs text-muted-foreground">{{ metodo.nombre }}</span>
                                    </div>

                                    <!-- Datos del método -->
                                    <div class="flex-1 space-y-1">
                                        <p class="font-medium">{{ metodo.nombre }}</p>
                                        <p v-if="metodo.destinatario" class="text-sm text-muted-foreground">
                                            {{ metodo.destinatario }}
                                        </p>
                                        <p v-if="metodo.numero_cuenta" class="text-sm font-mono text-muted-foreground">
                                            {{ metodo.numero_cuenta }}
                                        </p>
                                    </div>

                                    <!-- Eliminar -->
                                    <Button variant="ghost" size="sm" class="text-red-500 hover:text-red-600" @click="eliminarMetodoPago(index)">
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>

                            <div v-else class="rounded-lg border border-dashed p-8 text-center">
                                <CreditCard class="mx-auto h-8 w-8 text-muted-foreground" />
                                <p class="mt-2 text-sm text-muted-foreground">No has agregado métodos de pago</p>
                                <Button @click="abrirModalMetodos" variant="outline" size="sm" class="mt-3 gap-1">
                                    <Plus class="h-4 w-4" />
                                    Agregar método
                                </Button>
                            </div>
                        </div>
                    </CrmPanel>

                    <!-- Modal de Métodos de Pago -->
                    <Dialog :open="modalMetodosAbierto" @update:open="modalMetodosAbierto = $event">
                        <DialogContent class="max-w-2xl max-h-[90vh] overflow-y-auto">
                            <!-- Paso 1: Selección de método -->
                            <template v-if="pasoModal === 'seleccion'">
                                <DialogHeader>
                                    <DialogTitle class="flex items-center gap-2">
                                        <CreditCard class="h-5 w-5" />
                                        Selecciona un Método de Pago
                                    </DialogTitle>
                                    <DialogDescription>
                                        Elige el tipo de pago que deseas configurar
                                    </DialogDescription>
                                </DialogHeader>

                                <div class="space-y-6 py-4">
                                    <div v-for="categoria in METODOS_PREDEFINIDOS" :key="categoria.categoria">
                                        <h4 class="mb-3 text-sm font-semibold text-muted-foreground">
                                            {{ categoria.categoria }}
                                        </h4>
                                        <div class="grid grid-cols-4 gap-3">
                                            <button
                                                v-for="metodo in categoria.metodos"
                                                :key="metodo.nombre"
                                                class="flex flex-col items-center gap-2 rounded-lg border p-4 transition-all hover:border-primary hover:bg-accent"
                                                @click="seleccionarMetodo(metodo)"
                                            >
                                                <div
                                                    class="flex h-12 w-12 items-center justify-center rounded-lg"
                                                    :class="metodo.color"
                                                >
                                                    <component
                                                        :is="obtenerIconoComponente(metodo.icono)"
                                                        class="h-6 w-6"
                                                    />
                                                </div>
                                                <span class="text-xs font-medium">{{ metodo.nombre }}</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <!-- Paso 2: Formulario de detalles -->
                            <template v-else>
                                <DialogHeader>
                                    <div class="flex items-center gap-2">
                                        <Button variant="ghost" size="sm" class="h-8 w-8 p-0" @click="volverASeleccion">
                                            <ArrowLeftRight class="h-4 w-4" />
                                        </Button>
                                        <DialogTitle>
                                            Configurar {{ metodoSeleccionado?.nombre }}
                                        </DialogTitle>
                                    </div>
                                </DialogHeader>

                                <div class="space-y-4 py-4">
                                    <!-- QR / Imagen -->
                                    <div class="space-y-2">
                                        <Label>QR / Imagen del método</Label>
                                        <div class="flex items-center gap-4">
                                            <div
                                                v-if="previewImagenQr"
                                                class="h-20 w-20 rounded-lg border bg-cover bg-center"
                                                :style="{ backgroundImage: `url(${previewImagenQr})` }"
                                            />
                                            <div
                                                v-else
                                                class="flex h-20 w-20 flex-col items-center justify-center rounded-lg border border-dashed bg-muted"
                                            >
                                                <ImageIcon class="h-6 w-6 text-muted-foreground" />
                                                <span class="mt-1 text-[10px] text-muted-foreground">Sin QR</span>
                                            </div>
                                            <div class="flex-1">
                                                <Input
                                                    ref="inputQrFile"
                                                    type="file"
                                                    accept="image/*"
                                                    class="cursor-pointer"
                                                    @change="manejarArchivoQr"
                                                />
                                                <p class="mt-1 text-xs text-muted-foreground">
                                                    Sube una foto del QR (PNG, JPG). Máximo 2MB.
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Nombre -->
                                    <div class="space-y-2">
                                        <Label for="mp-nombre">Método de pago</Label>
                                        <Input id="mp-nombre" v-model="nuevoMetodoForm.nombre" readonly />
                                    </div>

                                    <!-- Destinatario -->
                                    <div class="space-y-2">
                                        <Label for="mp-destinatario">Destinatario</Label>
                                        <Input
                                            id="mp-destinatario"
                                            v-model="nuevoMetodoForm.destinatario"
                                            placeholder="Nombre del destinatario"
                                        />
                                    </div>

                                    <!-- Número de cuenta -->
                                    <div class="space-y-2">
                                        <Label for="mp-numero">Número de cuenta o celular</Label>
                                        <Input
                                            id="mp-numero"
                                            v-model="nuevoMetodoForm.numero_cuenta"
                                            type="tel"
                                            maxlength="9"
                                            placeholder="Ej: 912874650"
                                            :class="numeroCuentaValido ? 'border-green-500 focus-visible:ring-green-500' : nuevoMetodoForm.numero_cuenta ? 'border-red-500 focus-visible:ring-red-500' : ''"
                                            @input="validarNumeroCuenta"
                                        />
                                        <p v-if="nuevoMetodoForm.numero_cuenta && !numeroCuentaValido" class="text-xs text-red-500">
                                            Debe ser un número válido de Perú (9 dígitos, empieza con 9)
                                        </p>
                                        <p v-else-if="numeroCuentaValido" class="text-xs text-green-600">
                                            Número válido
                                        </p>
                                        <p v-else class="text-xs text-muted-foreground">
                                            Ingresa 9 dígitos para celular (ej: 912345678)
                                        </p>
                                    </div>

                                    <!-- Descripción adicional -->
                                    <div class="space-y-2">
                                        <Label for="mp-desc">Instrucciones adicionales (Opcional)</Label>
                                        <Textarea
                                            id="mp-desc"
                                            v-model="nuevoMetodoForm.descripcion"
                                            placeholder="Instrucciones específicas para este método..."
                                            :rows="2"
                                        />
                                    </div>
                                </div>

                                <div class="flex gap-2">
                                    <Button variant="outline" class="flex-1" @click="volverASeleccion">
                                        Volver
                                    </Button>
                                    <Button class="flex-1" @click="guardarMetodo">
                                        Guardar Método
                                    </Button>
                                </div>
                            </template>
                        </DialogContent>
                    </Dialog>

                    <CrmPanel>
                        <ConfigSectionHeader
                            :icon="CreditCard"
                            title="Comisión por tarjeta"
                            description="Porcentaje adicional si el cliente paga con tarjeta"
                        />
                        <div>
                            <div class="space-y-2">
                                <Label for="comision-tarjeta">Comisión (%)</Label>
                                <Input
                                    id="comision-tarjeta"
                                    v-model.number="form.comision_tarjeta"
                                    type="number"
                                    placeholder="5"
                                    min="0"
                                    max="100"
                                    step="0.01"
                                />
                            </div>
                        </div>
                    </CrmPanel>

                    <CrmPanel>
                        <ConfigSectionHeader
                            :icon="Link2"
                            title="Link de pago tarjeta"
                            description="URL que envías desde el chat. El mensaje al cliente es solo el link."
                        />
                        <div class="space-y-3">
                            <div class="space-y-2">
                                <Label for="link-pago-tarjeta">URL de pago</Label>
                                <Input
                                    id="link-pago-tarjeta"
                                    v-model="form.link_pago_tarjeta"
                                    type="url"
                                    placeholder="https://tu-pasarela.com/pagar?monto={total}&pedido={sale_id}"
                                />
                            </div>
                            <p class="text-xs text-muted-foreground">
                                Variables opcionales:
                                <code class="rounded bg-muted px-1">{total}</code>,
                                <code class="rounded bg-muted px-1">{sale_id}</code>,
                                <code class="rounded bg-muted px-1">{telefono}</code>
                            </p>
                        </div>
                    </CrmPanel>

                    <CrmPanel>
                        <ConfigSectionHeader
                            :icon="FileText"
                            title="Información adicional"
                            description="Políticas generales. Los métodos concretos (Yape, tarjeta) se configuran arriba."
                        />
                        <div class="space-y-4">
                            <div class="space-y-2">
                                <Label for="horario">Horario de Atención</Label>
                                <Textarea id="horario" v-model="form.horario_atencion" placeholder="Lunes a Viernes: 9am - 8pm&#10;Sábado: 10am - 6pm&#10;Domingo: Cerrado" :rows="3" />
                            </div>

                            <div class="space-y-2">
                                <Label for="politica">Política de Devoluciones</Label>
                                <Textarea id="politica" v-model="form.politica_devoluciones" placeholder="Describe tu política de devoluciones..." :rows="3" />
                            </div>

                            <div class="space-y-2">
                                <Label for="restricciones_especiales">Restricciones Especiales</Label>
                                <Textarea id="restricciones_especiales" v-model="form.restricciones_especiales" placeholder="Ej: No vendemos a menores de edad, No hacemos envíos internacionales..." :rows="3" />
                            </div>

                            <div class="space-y-2">
                                <Label for="info_extra">Información Adicional</Label>
                                <Textarea id="info_extra" v-model="form.informacion_adicional" placeholder="Cualquier otra información que la IA deba saber..." :rows="3" />
                            </div>
                        </div>
                    </CrmPanel>
                </TabsContent>

                <!-- TAB 5: FLUJO DE VENTAS -->
                <TabsContent value="flujo" class="mt-6 space-y-6">
                    <FlujoVentasTab />
                </TabsContent>

                <!-- TAB 6: ENTREGAS -->
                <TabsContent value="entregas" class="mt-6 space-y-6">
                    <EntregasTab />
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
                            description="Borra empresa, personalidad, pagos, flujo de ventas, zonas de delivery, API key de IA e historial de logs IA. No elimina productos, categorías ni conversaciones de WhatsApp."
                        />
                        <Button
                            variant="destructive"
                            class="gap-2"
                            :disabled="resetting"
                            @click="modalResetAbierto = true"
                        >
                            <Trash2 class="h-4 w-4" />
                            {{ resetting ? 'Restableciendo...' : 'Eliminar toda la configuración' }}
                        </Button>
                        </CrmPanel>
                    </div>

                    <Dialog v-model:open="modalResetAbierto">
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>¿Eliminar toda la configuración?</DialogTitle>
                                <DialogDescription>
                                    Esta acción no se puede deshacer. Escribe <strong>RESETEAR</strong> para confirmar.
                                </DialogDescription>
                            </DialogHeader>
                            <div class="space-y-2">
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
