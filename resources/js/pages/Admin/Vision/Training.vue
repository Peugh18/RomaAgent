<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import CrmAlert from '@/components/crm/CrmAlert.vue';
import CrmListCard from '@/components/crm/CrmListCard.vue';
import CrmAnimatedSection from '@/components/crm/CrmAnimatedSection.vue';
import CrmPageHero from '@/components/crm/CrmPageHero.vue';
import StatCard from '@/components/dashboard/StatCard.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import {
    Brain,
    CheckCircle2,
    Loader2,
    TrendingUp,
    XCircle,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Entrenamiento visión', href: '/admin/vision/training' },
];

interface FeedbackStat {
    periodo: string;
    feedback_positivo: number;
    feedback_negativo: number;
    tasa_acierto: number;
    productos_populares: Array<{
        id: number;
        name: string;
        color: string;
        vision_popularity: number;
    }>;
    tipos_prenda_rendimiento: Array<{
        tipo_prenda: string;
        total: number;
        aciertos: number;
    }>;
}

interface TrainingSession {
    id: number;
    product_name: string;
    variant_color: string;
    image_url: string | null;
    predicted_product: string;
    is_correct: boolean | null;
    created_at: string;
}

const stats = ref<FeedbackStat | null>(null);
const sessions = ref<TrainingSession[]>([]);
const loading = ref(false);
const loadError = ref<string | null>(null);
const activeTab = ref('stats');

const getCsrfToken = (): string =>
    document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

const accuracyVariant = computed(() => {
    const rate = stats.value?.tasa_acierto ?? 0;
    if (rate >= 85) return 'success' as const;
    if (rate >= 50) return 'warning' as const;
    return 'danger' as const;
});

const accuracyHint = computed(() => {
    const rate = stats.value?.tasa_acierto ?? 0;
    if (rate >= 85) return 'Excelente';
    if (rate >= 70) return 'Bueno';
    if (rate >= 50) return 'Regular';
    return 'Necesita mejora';
});

const loadStats = async () => {
    try {
        loading.value = true;
        loadError.value = null;
        const response = await fetch('/admin/vision/embeddings/learning-report', {
            headers: { Accept: 'application/json' },
        });
        if (!response.ok) throw new Error('No se pudieron cargar las estadísticas');
        stats.value = await response.json();
    } catch (err) {
        loadError.value = err instanceof Error ? err.message : 'Error desconocido';
    } finally {
        loading.value = false;
    }
};

const loadTrainingSessions = async () => {
    try {
        loading.value = true;
        const response = await fetch('/admin/vision/training-sessions', {
            headers: { Accept: 'application/json' },
        });
        if (!response.ok) throw new Error('No se pudieron cargar las sesiones');
        sessions.value = await response.json();
    } catch (err) {
        loadError.value = err instanceof Error ? err.message : 'Error desconocido';
    } finally {
        loading.value = false;
    }
};

const submitFeedback = async (sessionId: number, isCorrect: boolean) => {
    try {
        const response = await fetch('/admin/vision/feedback', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify({
                session_id: sessionId,
                is_correct: isCorrect,
            }),
        });

        if (!response.ok) throw new Error('Error al enviar feedback');

        const session = sessions.value.find((s) => s.id === sessionId);
        if (session) session.is_correct = isCorrect;

        await loadStats();
    } catch {
        loadError.value = 'No se pudo registrar el feedback.';
    }
};

onMounted(async () => {
    await Promise.all([loadStats(), loadTrainingSessions()]);
});
</script>

<template>
    <Head title="Entrenamiento visión" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="crm-page">
            <CrmPageHero
                title="Entrenamiento visión"
                description="Métricas y feedback para afinar el reconocimiento de productos por imagen."
                :icon="Brain"
                variant="violet"
            />

            <CrmAlert v-if="loadError" variant="error">{{ loadError }}</CrmAlert>

            <CrmAnimatedSection :delay="80">
            <Tabs v-model="activeTab" class="w-full">
                <TabsList class="grid w-full max-w-md grid-cols-2">
                    <TabsTrigger value="stats" class="gap-2">
                        <TrendingUp class="h-4 w-4" />
                        Estadísticas
                    </TabsTrigger>
                    <TabsTrigger value="training" class="gap-2">
                        <Brain class="h-4 w-4" />
                        Sesiones
                    </TabsTrigger>
                </TabsList>

                <TabsContent value="stats" class="mt-6 space-y-6">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <StatCard
                            title="Feedback positivo"
                            :value="stats?.feedback_positivo ?? 0"
                            :icon="CheckCircle2"
                            :loading="loading"
                            variant="success"
                        />
                        <StatCard
                            title="Feedback negativo"
                            :value="stats?.feedback_negativo ?? 0"
                            :icon="XCircle"
                            :loading="loading"
                            variant="danger"
                        />
                        <StatCard
                            title="Tasa de acierto"
                            :value="`${stats?.tasa_acierto ?? 0}%`"
                            :icon="TrendingUp"
                            :loading="loading"
                            :variant="accuracyVariant"
                        />
                    </div>
                    <p class="text-sm text-muted-foreground">{{ accuracyHint }}</p>

                    <div v-if="stats?.tipos_prenda_rendimiento?.length" class="rounded-xl border border-border/80 bg-card shadow-sm">
                        <div class="border-b border-border/60 px-6 py-4">
                            <h3 class="text-base font-semibold">Rendimiento por tipo de prenda</h3>
                        </div>
                        <div class="space-y-3 p-6">
                            <div
                                v-for="tipo in stats.tipos_prenda_rendimiento"
                                :key="tipo.tipo_prenda"
                                class="flex items-center justify-between rounded-lg border border-border/60 bg-muted/30 px-4 py-3"
                            >
                                <div>
                                    <p class="font-medium capitalize">{{ tipo.tipo_prenda }}</p>
                                    <p class="text-sm text-muted-foreground">{{ tipo.total }} predicciones</p>
                                </div>
                                <div class="text-right">
                                    <p class="font-semibold">
                                        {{ tipo.total > 0 ? Math.round((tipo.aciertos / tipo.total) * 100) : 0 }}%
                                    </p>
                                    <p class="text-xs text-muted-foreground">{{ tipo.aciertos }} aciertos</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="stats?.productos_populares?.length" class="rounded-xl border border-border/80 bg-card shadow-sm">
                        <div class="border-b border-border/60 px-6 py-4">
                            <h3 class="text-base font-semibold">Productos más consultados</h3>
                        </div>
                        <div class="space-y-3 p-6">
                            <div
                                v-for="product in stats.productos_populares"
                                :key="product.id"
                                class="flex items-center justify-between rounded-lg border border-border/60 bg-muted/30 px-4 py-3"
                            >
                                <div>
                                    <p class="font-medium">{{ product.name }}</p>
                                    <p class="text-sm text-muted-foreground">{{ product.color }}</p>
                                </div>
                                <Badge variant="secondary">{{ product.vision_popularity }} matches</Badge>
                            </div>
                        </div>
                    </div>
                </TabsContent>

                <TabsContent value="training" class="mt-6">
                    <div v-if="loading" class="flex items-center justify-center py-16 text-muted-foreground">
                        <Loader2 class="mr-2 h-5 w-5 animate-spin" />
                        Cargando sesiones…
                    </div>

                    <CrmListCard v-else-if="sessions.length === 0">
                        <div class="py-16 text-center">
                            <Brain class="mx-auto mb-4 h-10 w-10 text-muted-foreground" />
                            <p class="font-medium">Sin sesiones todavía</p>
                            <p class="mt-1 text-sm text-muted-foreground">
                                Aparecerán cuando el chat analice imágenes y registre predicciones.
                            </p>
                        </div>
                    </CrmListCard>

                    <div v-else class="grid gap-4 lg:grid-cols-2">
                        <Card v-for="session in sessions" :key="session.id" class="overflow-hidden border-border/80 shadow-sm">
                            <CardHeader class="border-b border-border/60 pb-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <CardTitle class="text-base">{{ session.product_name }}</CardTitle>
                                        <p class="text-sm text-muted-foreground">{{ session.variant_color }}</p>
                                    </div>
                                    <Badge
                                        v-if="session.is_correct === true"
                                        class="bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300"
                                    >
                                        Correcto
                                    </Badge>
                                    <Badge v-else-if="session.is_correct === false" variant="destructive">
                                        Incorrecto
                                    </Badge>
                                    <Badge v-else variant="secondary">Pendiente</Badge>
                                </div>
                            </CardHeader>
                            <CardContent class="space-y-4 pt-4">
                                <div class="flex aspect-video items-center justify-center rounded-lg bg-muted">
                                    <img
                                        v-if="session.image_url"
                                        :src="session.image_url"
                                        alt="Referencia"
                                        class="max-h-full max-w-full rounded-lg object-contain"
                                    />
                                    <span v-else class="text-sm text-muted-foreground">Sin imagen</span>
                                </div>
                                <div>
                                    <p class="text-xs text-muted-foreground">Predicción</p>
                                    <p class="font-medium">{{ session.predicted_product }}</p>
                                </div>
                                <div v-if="session.is_correct === null" class="flex gap-2">
                                    <Button class="flex-1 gap-2" @click="submitFeedback(session.id, true)">
                                        <CheckCircle2 class="h-4 w-4" />
                                        Correcto
                                    </Button>
                                    <Button variant="destructive" class="flex-1 gap-2" @click="submitFeedback(session.id, false)">
                                        <XCircle class="h-4 w-4" />
                                        Incorrecto
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </TabsContent>
            </Tabs>
            </CrmAnimatedSection>
        </div>
    </AppLayout>
</template>
