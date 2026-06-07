<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { SALE_STATUS_LABELS, type SaleStatus } from '@/types/sale';
import { useCurrency } from '@/composables/useCurrency';
import { Link } from '@inertiajs/vue3';
import { ArrowRight, MessageCircle, Package, TrendingUp } from 'lucide-vue-next';

interface RecentOrder {
    id: number;
    product_name: string;
    color: string | null;
    phone_number: string;
    customer_name: string | null;
    total_amount: number;
    status: SaleStatus;
    status_label: string;
    created_at: string | null;
}

defineProps<{
    orders: RecentOrder[];
    loading?: boolean;
}>();

const { format: formatMoney } = useCurrency();

const statusBadge = (status: SaleStatus) => {
    const styles: Record<string, string> = {
        consultando: 'bg-slate-100 text-slate-700 dark:bg-slate-900 dark:text-slate-300',
        cotizando: 'bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-300',
        datos_listos: 'bg-violet-50 text-violet-700 dark:bg-violet-950 dark:text-violet-300',
        pago_pendiente: 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
        pago_recibido: 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
        confirmado: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
        enviado: 'bg-sky-50 text-sky-700 dark:bg-sky-950 dark:text-sky-300',
        entregado: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
        cancelado: 'bg-rose-50 text-rose-700 dark:bg-rose-950 dark:text-rose-300',
    };

    return styles[status] || styles.consultando;
};

const formatRelative = (value: string | null): string => {
    if (!value) {
        return '';
    }

    const date = new Date(value);
    const diffMs = Date.now() - date.getTime();
    const diffHours = Math.floor(diffMs / (1000 * 60 * 60));

    if (diffHours < 1) {
        return 'Hace un momento';
    }

    if (diffHours < 24) {
        return `Hace ${diffHours}h`;
    }

    const diffDays = Math.floor(diffHours / 24);

    return diffDays === 1 ? 'Ayer' : `Hace ${diffDays} días`;
};

const displayLabel = (order: RecentOrder): string => {
    if (order.customer_name) {
        return order.customer_name;
    }

    return order.product_name;
};
</script>

<template>
    <Card class="col-span-full border-0 shadow-sm lg:col-span-2">
        <CardHeader class="pb-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <Package class="h-4 w-4 text-muted-foreground" />
                    <CardTitle class="text-base font-semibold">Pedidos Recientes</CardTitle>
                </div>
                <Button variant="ghost" size="sm" class="h-8 gap-1 text-xs" as-child>
                    <Link href="/pipeline">
                        Ver pipeline
                        <ArrowRight class="h-3.5 w-3.5" />
                    </Link>
                </Button>
            </div>
        </CardHeader>
        <CardContent class="pt-0">
            <div v-if="loading" class="space-y-3">
                <div v-for="i in 5" :key="i" class="flex items-center gap-3">
                    <Skeleton class="h-10 w-10 rounded-lg" />
                    <div class="flex-1 space-y-2">
                        <Skeleton class="h-4 w-32" />
                        <Skeleton class="h-3 w-24" />
                    </div>
                    <Skeleton class="h-4 w-16" />
                </div>
            </div>

            <div v-else-if="orders.length === 0" class="flex flex-col items-center justify-center py-12 text-center">
                <div class="rounded-full bg-muted p-3">
                    <TrendingUp class="h-5 w-5 text-muted-foreground" />
                </div>
                <p class="mt-3 text-sm text-muted-foreground">Aún no hay pedidos registrados</p>
                <p class="text-xs text-muted-foreground">La IA creará pedidos automáticamente desde WhatsApp</p>
            </div>

            <div v-else class="divide-y divide-border">
                <Link
                    v-for="order in orders.slice(0, 6)"
                    :key="order.id"
                    :href="`/chat?phone=${encodeURIComponent(order.phone_number)}`"
                    class="group flex items-center gap-3 py-3 transition-colors first:pt-0 last:pb-0 hover:bg-muted/40 -mx-2 rounded-lg px-2"
                >
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-muted group-hover:bg-background">
                        <Package class="h-4 w-4 text-muted-foreground" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium">
                            {{ displayLabel(order) }}
                            <span v-if="order.customer_name && order.color" class="text-muted-foreground">· {{ order.color }}</span>
                            <span v-else-if="order.color" class="text-muted-foreground">· {{ order.color }}</span>
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ order.phone_number }}
                            <span v-if="order.created_at"> · {{ formatRelative(order.created_at) }}</span>
                        </p>
                    </div>
                    <div class="shrink-0 text-right">
                        <p class="text-sm font-semibold">{{ formatMoney(order.total_amount) }}</p>
                        <span
                            class="inline-flex rounded px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide"
                            :class="statusBadge(order.status)"
                        >
                            {{ SALE_STATUS_LABELS[order.status] ?? order.status_label }}
                        </span>
                    </div>
                    <MessageCircle class="h-4 w-4 shrink-0 text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100" />
                </Link>
            </div>
        </CardContent>
    </Card>
</template>
