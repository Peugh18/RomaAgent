<script setup lang="ts">
import { ref, inject } from 'vue';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import CrmPanel from '@/components/crm/CrmPanel.vue';
import ConfigSectionHeader from '@/components/configuracion/ConfigSectionHeader.vue';
import { DollarSign, CreditCard, Plus, Trash2, QrCode, ImageIcon, ArrowLeftRight, Smartphone, Landmark, Globe, Wallet, Banknote } from 'lucide-vue-next';
import type { CompanySettingsForm } from '@/types/settings';

const form = inject('companyConfigForm') as CompanySettingsForm & Record<string, unknown>;
const monedas = inject('monedas') as Array<{ code: string, label: string }>;

if (!form) {
    throw new Error('companyConfigForm no está disponible');
}

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
        ],
    },
];

const obtenerIconoComponente = (nombreIcono: string) => {
    const iconos: Record<string, any> = {
        Smartphone,
        Landmark,
        CreditCard,
        Wallet,
        Banknote,
        ArrowLeftRight,
        Globe,
    };
    return iconos[nombreIcono] || CreditCard;
};

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

const volverASeleccion = () => {
    pasoModal.value = 'seleccion';
};

const guardarMetodo = () => {
    if (!form.metodos_pago) {
        form.metodos_pago = [];
    }
    form.metodos_pago.push({ ...nuevoMetodoForm.value });
    modalMetodosAbierto.value = false;
};

const eliminarMetodoPago = (index: number) => {
    if (form.metodos_pago) {
        form.metodos_pago.splice(index, 1);
    }
};
</script>

<template>
    <div class="space-y-6">


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
                <div v-if="form.metodos_pago && form.metodos_pago.length > 0" class="grid gap-3">
                    <div
                        v-for="(metodo, index) in form.metodos_pago"
                        :key="index"
                        class="flex items-center gap-4 rounded-lg border p-4"
                    >
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

                        <div class="flex-1 space-y-1">
                            <p class="font-medium">{{ metodo.nombre }}</p>
                            <p v-if="metodo.destinatario" class="text-sm text-muted-foreground">
                                {{ metodo.destinatario }}
                            </p>
                            <p v-if="metodo.numero_cuenta" class="text-sm font-mono text-muted-foreground">
                                {{ metodo.numero_cuenta }}
                            </p>
                        </div>

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

        <Dialog :open="modalMetodosAbierto" @update:open="modalMetodosAbierto = $event">
            <DialogContent class="max-w-2xl max-h-[90vh] overflow-y-auto">
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

                        <div class="space-y-2">
                            <Label for="mp-nombre">Método de pago</Label>
                            <Input id="mp-nombre" v-model="nuevoMetodoForm.nombre" readonly />
                        </div>

                        <div class="space-y-2">
                            <Label for="mp-destinatario">Destinatario</Label>
                            <Input
                                id="mp-destinatario"
                                v-model="nuevoMetodoForm.destinatario"
                                placeholder="Nombre del destinatario"
                            />
                        </div>

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



    </div>
</template>
