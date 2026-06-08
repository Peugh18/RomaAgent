<script setup lang="ts">
import { Button } from '@/components/ui/button';
import NumberFlow from '@number-flow/vue';
import { useCurrency } from '@/composables/useCurrency';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { format } from 'date-fns';
import { es } from 'date-fns/locale';
import { Kanban, MessageSquare, Package, Sparkles } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    ventasMes: number;
    pedidosActivos: number;
}>();

const page = usePage<SharedData>();
const { format: formatMoney } = useCurrency();

const userName = computed(() => page.props.auth.user.name.split(' ')[0] ?? 'equipo');

const greeting = computed(() => {
    const hour = new Date().getHours();

    if (hour < 12) {
        return 'Buenos días';
    }

    if (hour < 19) {
        return 'Buenas tardes';
    }

    return 'Buenas noches';
});

const todayLabel = computed(() => format(new Date(), "EEEE d 'de' MMMM", { locale: es }));
</script>

<template>
    <div
        v-motion
        :initial="{ opacity: 0, y: 12 }"
        :enter="{ opacity: 1, y: 0, transition: { duration: 350, ease: 'easeOut' } }"
        class="relative overflow-hidden rounded-2xl border border-emerald-500/20 bg-gradient-to-br from-emerald-500/10 via-card to-card p-6 shadow-sm"
    >
        <div class="pointer-events-none absolute -right-8 -top-8 h-40 w-40 rounded-full bg-emerald-500/10 blur-3xl" />
        <div class="pointer-events-none absolute -bottom-10 left-1/3 h-32 w-32 rounded-full bg-emerald-400/5 blur-2xl" />

        <div class="relative flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
            <div class="space-y-2">
                <p class="text-xs font-medium uppercase tracking-widest text-emerald-600/80 dark:text-emerald-400/80">
                    {{ todayLabel }}
                </p>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">
                    {{ greeting }}, {{ userName }}
                </h2>
                <p class="max-w-xl text-sm text-muted-foreground">
                    Resumen de ventas y operaciones de RomaAgent. Este mes llevas
                    <span class="font-semibold text-foreground">{{ formatMoney(ventasMes) }}</span>
                    confirmados.
                </p>
                <div class="flex flex-wrap items-center gap-3 pt-1">
                    <div class="inline-flex items-center gap-2 rounded-full border border-border/60 bg-background/60 px-3 py-1 text-xs backdrop-blur-sm">
                        <Sparkles class="h-3.5 w-3.5 text-emerald-500" />
                        <span class="text-muted-foreground">Activos en pipeline:</span>
                        <NumberFlow :value="pedidosActivos" class="font-semibold text-foreground" />
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap gap-2 lg:justify-end">
                <Button as-child size="sm" class="gap-2 bg-emerald-600 text-white hover:bg-emerald-700">
                    <Link href="/chat">
                        <MessageSquare class="h-4 w-4" />
                        Abrir chat
                    </Link>
                </Button>
                <Button as-child size="sm" variant="secondary" class="gap-2">
                    <Link href="/pipeline">
                        <Kanban class="h-4 w-4" />
                        Pipeline
                    </Link>
                </Button>
                <Button as-child size="sm" variant="outline" class="gap-2 border-border/70 bg-background/50">
                    <Link href="/productos">
                        <Package class="h-4 w-4" />
                        Productos
                    </Link>
                </Button>
            </div>
        </div>
    </div>
</template>
