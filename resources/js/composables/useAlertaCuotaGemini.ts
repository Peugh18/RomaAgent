import { apiJson } from '@/composables/useApi';
import { onMounted, onUnmounted, ref } from 'vue';

export interface AlertaCuotaGemini {
    activa: boolean;
    titulo: string;
    descripcion: string;
    mensaje: string;
    detectado_en: string;
    disponible_aprox_en: string;
    reintentar_en_segundos: number;
    segundos_restantes: number;
    puede_reintentar: boolean;
    desde_log?: boolean;
}

const POLL_INTERVAL_MS = 30_000;

const alerta = ref<AlertaCuotaGemini | null>(null);
const cargando = ref(false);
let pollTimer: ReturnType<typeof setInterval> | null = null;
let suscriptores = 0;

async function refrescar(): Promise<void> {
    cargando.value = true;

    try {
        const datos = await apiJson<{ alerta: AlertaCuotaGemini | null }>('/api/alerta-cuota-gemini');
        alerta.value = datos.alerta?.activa ? datos.alerta : null;
    } catch {
        // Mantener último estado conocido si falla la red.
    } finally {
        cargando.value = false;
    }
}

function iniciarPolling(): void {
    if (pollTimer !== null) {
        return;
    }

    void refrescar();
    pollTimer = setInterval(() => {
        void refrescar();
    }, POLL_INTERVAL_MS);
}

function detenerPolling(): void {
    if (pollTimer !== null) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
}

export function useAlertaCuotaGemini() {
    onMounted(() => {
        suscriptores += 1;
        iniciarPolling();
    });

    onUnmounted(() => {
        suscriptores = Math.max(0, suscriptores - 1);

        if (suscriptores === 0) {
            detenerPolling();
        }
    });

    return {
        alerta,
        cargando,
        refrescar,
    };
}
