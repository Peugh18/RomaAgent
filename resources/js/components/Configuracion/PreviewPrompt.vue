<script setup lang="ts">
import { computed } from 'vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Copy, Download, Zap, CheckCircle, AlertCircle } from 'lucide-vue-next';

interface Props {
    promptCompleto: string;
    promptPreview: string;
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

const promptVisible = computed(() => props.promptCompleto || props.promptPreview || '');

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
        <!-- Estadísticas -->
        <Card>
            <CardHeader class="pb-3">
                <CardTitle class="text-lg">Estadísticas</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <!-- Completitud -->
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-medium">Completitud</span>
                        <span :class="['text-sm font-bold', porcentajeColor]">
                            {{ est.completitud }}%
                        </span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2 dark:bg-gray-700">
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
            </CardContent>
        </Card>

        <!-- Preview del Prompt -->
        <Card>
            <CardHeader class="pb-3">
                <CardTitle class="text-lg flex items-center gap-2">
                    <Zap class="h-5 w-5" />
                    Preview del Prompt
                </CardTitle>
                <CardDescription>Prompt completo que usa la IA. Se actualiza al guardar.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-3">
                <div class="max-h-96 overflow-y-auto rounded-lg border border-gray-200 bg-gray-900 p-3 dark:border-gray-700">
                    <pre class="text-xs text-gray-100 font-mono whitespace-pre-wrap break-words">{{ promptVisible }}</pre>
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

                <Button @click="emit('probarIA')" :disabled="!est.esta_lista || props.probando" class="w-full gap-2">
                    <Zap class="h-4 w-4" />
                    {{ props.probando ? 'Probando...' : 'Probar IA' }}
                </Button>

                <p v-if="props.resultadoPrueba" class="rounded-md bg-green-50 p-2 text-xs text-green-800 dark:bg-green-950 dark:text-green-200">
                    {{ props.resultadoPrueba }}
                </p>
                <p v-if="props.errorPrueba" class="rounded-md bg-red-50 p-2 text-xs text-red-800 dark:bg-red-950 dark:text-red-200">
                    {{ props.errorPrueba }}
                </p>
            </CardContent>
        </Card>
    </div>
</template>
