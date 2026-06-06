<script setup lang="ts">
import { useAlertaCuotaGemini } from '@/composables/useAlertaCuotaGemini';
import { Link } from '@inertiajs/vue3';
import { AlertTriangle, ExternalLink } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const { alerta, refrescar } = useAlertaCuotaGemini();

const segundosRestantes = ref(0);
let countdownTimer: ReturnType<typeof setInterval> | null = null;

const textoCuentaRegresiva = computed(() => {
    if (segundosRestantes.value <= 0) {
        return 'Reintentando automáticamente…';
    }

    const minutos = Math.floor(segundosRestantes.value / 60);
    const segundos = segundosRestantes.value % 60;

    if (minutos > 0) {
        return `Reintento estimado en ${minutos}m ${segundos}s`;
    }

    return `Reintento estimado en ${segundos}s`;
});

function sincronizarCuentaRegresiva(): void {
    segundosRestantes.value = alerta.value?.segundos_restantes ?? 0;
}

function iniciarCuentaRegresiva(): void {
    detenerCuentaRegresiva();
    sincronizarCuentaRegresiva();

    countdownTimer = setInterval(() => {
        if (segundosRestantes.value > 0) {
            segundosRestantes.value -= 1;
        }
    }, 1000);
}

function detenerCuentaRegresiva(): void {
    if (countdownTimer !== null) {
        clearInterval(countdownTimer);
        countdownTimer = null;
    }
}

watch(alerta, (nueva) => {
    if (nueva) {
        iniciarCuentaRegresiva();
    } else {
        detenerCuentaRegresiva();
    }
}, { immediate: true });

watch(segundosRestantes, (valor) => {
    if (valor === 0 && alerta.value) {
        void refrescar();
    }
});

onMounted(() => {
    void refrescar();
});

onUnmounted(() => {
    detenerCuentaRegresiva();
});
</script>

<template>
    <div
        v-if="alerta"
        class="border-b border-amber-300 bg-amber-50 px-4 py-3 text-amber-950 dark:border-amber-700 dark:bg-amber-950/50 dark:text-amber-100"
        role="alert"
    >
        <div class="mx-auto flex max-w-6xl flex-wrap items-start gap-3">
            <AlertTriangle class="mt-0.5 h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400" />

            <div class="min-w-0 flex-1 space-y-1">
                <p class="text-sm font-semibold">{{ alerta.titulo }}</p>
                <p class="text-sm">{{ alerta.descripcion }}</p>
                <p class="text-xs text-amber-800/90 dark:text-amber-200/80">
                    {{ textoCuentaRegresiva }}
                </p>
                <p v-if="alerta.mensaje" class="truncate text-xs text-amber-700/80 dark:text-amber-300/70">
                    {{ alerta.mensaje }}
                </p>
            </div>

            <Link
                href="/configuracion/empresa"
                class="inline-flex shrink-0 items-center gap-1.5 rounded-md bg-amber-600 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-amber-700"
            >
                Revisar API key
                <ExternalLink class="h-3.5 w-3.5" />
            </Link>
        </div>
    </div>
</template>
