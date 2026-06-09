<script setup lang="ts">
import { computed, ref } from 'vue';
import CrmPanel from '@/components/crm/CrmPanel.vue';
import { Button } from '@/components/ui/button';
import { Copy, Download, Zap, CheckCircle, AlertCircle } from 'lucide-vue-next';

type PromptTab = 'sistema' | 'configuracion' | 'completo';

interface Props {
    promptCompleto: string;
    promptSecciones?: {
        sistema: string;
        configuracion: string;
        completo: string;
    };
    probando?: boolean;
    resultadoPrueba?: string | null;
    errorPrueba?: string | null;
    estadisticas?: {
        completitud?: number;
        campos_faltantes?: string[];
        productos_activos?: number;
        zonas_delivery?: number;
        metodos_pago_count?: number;
        tokens_estimados?: {
            entrada: number;
            salida: number;
            total: number;
        };
        esta_lista?: boolean;
        advertencias?: string[];
    };
}

const props = defineProps<Props>();

const activeTab = ref<PromptTab>('sistema');

const promptVisible = computed(() => {
    const secciones = props.promptSecciones;
    if (!secciones) return props.promptCompleto || 'Cargando prompt...';

    switch (activeTab.value) {
        case 'sistema':
            return secciones.sistema || 'Sin contenido en SISTEMA';
        case 'configuracion':
            return secciones.configuracion || 'Sin contenido en CONFIGURACIÓN';
        case 'completo':
        default:
            return secciones.completo || props.promptCompleto || 'Cargando prompt...';
    }
});

const est = computed(() => {
    return {
        completitud: props.estadisticas?.completitud ?? 0,
        campos_faltantes: props.estadisticas?.campos_faltantes ?? [],
        productos_activos: props.estadisticas?.productos_activos ?? 0,
        zonas_delivery: props.estadisticas?.zonas_delivery ?? 0,
        metodos_pago_count: props.estadisticas?.metodos_pago_count ?? 0,
        tokens_estimados: props.estadisticas?.tokens_estimados ?? { entrada: 0, salida: 0, total: 0 },
        esta_lista: props.estadisticas?.esta_lista ?? false,
        advertencias: props.estadisticas?.advertencias ?? [],
    };
});

const emit = defineEmits<{
    probarIA: [];
}>();

const porcentajeColor = computed(() => {
    const p = est.value.completitud;
    if (p >= 80) return 'text-green-600 dark:text-green-400';
    if (p >= 50) return 'text-yellow-600 dark:text-yellow-400';
    return 'text-red-600 dark:text-red-400';
});

const copiarPrompt = () => {
    navigator.clipboard.writeText(props.promptCompleto);
};

const descargarPrompt = () => {
    const elemento = document.createElement('a');
    elemento.href = 'data:text/plain;charset=utf-8,' + encodeURIComponent(props.promptCompleto);
    elemento.download = 'prompt-maestro.txt';
    elemento.click();
};
</script>

<template>
    <div class="sticky top-6 space-y-4">
        <CrmPanel title="Estadísticas">
            <div class="space-y-4">
                <!-- Completitud -->
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-medium">Completitud</span>
                        <span :class="['text-sm font-bold', porcentajeColor]">
                            {{ est.completitud }}%
                        </span>
                    </div>
                    <div class="h-2 w-full rounded-full bg-muted">
                        <div
                            class="h-2 rounded-full transition-all duration-300"
                            :class="
                                est.completitud >= 80
                                    ? 'bg-green-600'
                                    : est.completitud >= 50
                                      ? 'bg-yellow-600'
                                      : 'bg-red-600'
                            "
                            :style="{ width: `${est.completitud}%` }"
                        />
                    </div>
                </div>

                <!-- Campos faltantes -->
                <div v-if="est.campos_faltantes.length > 0" class="rounded-lg bg-amber-50 p-3 dark:bg-amber-950">
                    <p class="text-xs font-medium text-amber-900 dark:text-amber-200 mb-2">Campos faltantes:</p>
                    <ul class="space-y-1">
                        <li v-for="campo in est.campos_faltantes" :key="campo" class="text-xs text-amber-800 dark:text-amber-300">
                            • {{ campo }}
                        </li>
                    </ul>
                </div>

                <!-- Resumen -->
                <div class="grid grid-cols-2 gap-2 text-xs">
                    <div class="rounded bg-blue-50 p-2 dark:bg-blue-950">
                        <p class="text-blue-600 dark:text-blue-400 font-medium">{{ est.productos_activos }}</p>
                        <p class="text-blue-700 dark:text-blue-300">Productos</p>
                    </div>
                    <div class="rounded bg-purple-50 p-2 dark:bg-purple-950">
                        <p class="text-purple-600 dark:text-purple-400 font-medium">{{ est.zonas_delivery }}</p>
                        <p class="text-purple-700 dark:text-purple-300">Zonas</p>
                    </div>
                    <div class="rounded bg-green-50 p-2 dark:bg-green-950">
                        <p class="text-green-600 dark:text-green-400 font-medium">{{ est.metodos_pago_count }}</p>
                        <p class="text-green-700 dark:text-green-300">Métodos</p>
                    </div>
                    <div class="rounded bg-orange-50 p-2 dark:bg-orange-950">
                        <p class="text-orange-600 dark:text-orange-400 font-medium">{{ est.tokens_estimados.total }}</p>
                        <p class="text-orange-700 dark:text-orange-300">Tokens</p>
                    </div>
                </div>

                <!-- Estado -->
                <div v-if="est.esta_lista" class="rounded-lg bg-green-50 p-3 dark:bg-green-950 flex items-start gap-2">
                    <CheckCircle class="h-5 w-5 text-green-600 dark:text-green-400 flex-shrink-0 mt-0.5" />
                    <div>
                        <p class="text-sm font-medium text-green-900 dark:text-green-200">¡Listo para vender!</p>
                        <p class="text-xs text-green-700 dark:text-green-300">Tu configuración está completa</p>
                    </div>
                </div>
                <div v-else class="rounded-lg bg-red-50 p-3 dark:bg-red-950 flex items-start gap-2">
                    <AlertCircle class="h-5 w-5 text-red-600 dark:text-red-400 flex-shrink-0 mt-0.5" />
                    <div>
                        <p class="text-sm font-medium text-red-900 dark:text-red-200">Configuración incompleta</p>
                        <p class="text-xs text-red-700 dark:text-red-300">Completa los campos faltantes</p>
                    </div>
                </div>

                <div v-if="est.advertencias.length > 0" class="rounded-lg bg-amber-50 p-3 dark:bg-amber-950">
                    <p class="text-xs font-medium text-amber-900 dark:text-amber-200 mb-2">Pendiente:</p>
                    <ul class="space-y-1">
                        <li v-for="aviso in est.advertencias" :key="aviso" class="text-xs text-amber-800 dark:text-amber-300">
                            • {{ aviso }}
                        </li>
                    </ul>
                </div>
            </div>
        </CrmPanel>

        <CrmPanel title="Preview del Prompt" description="Prompt completo que usa la IA. Se actualiza al guardar.">
            <div class="space-y-3">
                <!-- Tabs -->
                <div class="flex gap-1 rounded-lg bg-muted p-1">
                    <button
                        class="flex-1 rounded-md px-2 py-1.5 text-xs font-medium transition-colors"
                        :class="activeTab === 'sistema'
                            ? 'bg-background text-foreground shadow-sm'
                            : 'text-muted-foreground hover:text-foreground'"
                        @click="activeTab = 'sistema'"
                    >
                        SISTEMA
                    </button>
                    <button
                        class="flex-1 rounded-md px-2 py-1.5 text-xs font-medium transition-colors"
                        :class="activeTab === 'configuracion'
                            ? 'bg-background text-foreground shadow-sm'
                            : 'text-muted-foreground hover:text-foreground'"
                        @click="activeTab = 'configuracion'"
                    >
                        CONFIGURACIÓN
                    </button>
                    <button
                        class="flex-1 rounded-md px-2 py-1.5 text-xs font-medium transition-colors"
                        :class="activeTab === 'completo'
                            ? 'bg-background text-foreground shadow-sm'
                            : 'text-muted-foreground hover:text-foreground'"
                        @click="activeTab = 'completo'"
                    >
                        COMPLETO
                    </button>
                </div>

                <div class="max-h-96 overflow-y-auto rounded-xl border border-border/60 bg-zinc-950 p-3">
                    <pre class="whitespace-pre-wrap break-words font-mono text-xs text-zinc-100">{{ promptVisible }}</pre>
                </div>

                <div class="flex gap-2">
                    <Button @click="copiarPrompt" variant="outline" size="sm" class="flex-1 gap-2">
                        <Copy class="h-4 w-4" />
                        Copiar
                    </Button>
                    <Button @click="descargarPrompt" variant="outline" size="sm" class="flex-1 gap-2">
                        <Download class="h-4 w-4" />
                        Descargar
                    </Button>
                </div>

                <Button
                    @click="emit('probarIA')"
                    :disabled="!est.esta_lista || props.probando"
                    class="w-full gap-2 bg-emerald-600 hover:bg-emerald-700"
                >
                    <Zap class="h-4 w-4" />
                    {{ props.probando ? 'Probando...' : 'Probar IA' }}
                </Button>

                <p v-if="props.resultadoPrueba" class="rounded-md bg-green-50 p-2 text-xs text-green-800 dark:bg-green-950 dark:text-green-200">
                    {{ props.resultadoPrueba }}
                </p>
                <p v-if="props.errorPrueba" class="rounded-md bg-red-50 p-2 text-xs text-red-800 dark:bg-red-950 dark:text-red-200">
                    {{ props.errorPrueba }}
                </p>
            </div>
        </CrmPanel>
    </div>
</template>
