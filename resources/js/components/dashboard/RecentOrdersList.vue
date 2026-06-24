<script setup lang="ts">
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { getInitials } from '@/composables/useInitials';
import { saleStatusChartColor } from '@/lib/dashboardStatusColors';
import { SALE_STATUS_LABELS, type SaleStatus } from '@/types/sale';
import { useCurrency } from '@/composables/useCurrency';
import { Link } from '@inertiajs/vue3';
import { formatDistanceToNow } from 'date-fns';
import { es } from 'date-fns/locale';
import { ArrowRight, MessageCircle, ShoppingBag } from 'lucide-vue-next';

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

const formatRelative = (value: string | null): string => {
    if (!value) {
        return '';
    }

    return formatDistanceToNow(new Date(value), { addSuffix: true, locale: es });
};

const displayLabel = (order: RecentOrder): string => order.customer_name ?? order.product_name;

const statusBadge = (status: SaleStatus): string => {
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
</script>

<template>
    <Card class="border border-border/50 shadow-sm">
        <CardHeader class="pb-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div class="rounded-lg bg-muted p-2">
                        <ShoppingBag class="h-4 w-4 text-muted-foreground" />
                    </div>
                    <div>
                        <CardTitle class="text-base font-semibold">Pedidos recientes</CardTitle>
                        <p class="text-xs text-muted-foreground">Última actividad del CRM</p>
                    </div>
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
                    <Skeleton class="h-10 w-10 rounded-full" />
                    <div class="flex-1 space-y-2">
                        <Skeleton class="h-4 w-32" />
                        <Skeleton class="h-3 w-24" />
                    </div>
                    <Skeleton class="h-4 w-16" />
                </div>
            </div>

            <div v-else-if="orders.length === 0" class="flex flex-col items-center justify-center py-12 text-center">
                <div class="rounded-full bg-muted p-4">
                    <ShoppingBag class="h-6 w-6 text-muted-foreground" />
                </div>
                <p class="mt-3 text-sm font-medium text-muted-foreground">Aún no hay pedidos registrados</p>
                <p class="text-xs text-muted-foreground">La IA creará pedidos automáticamente desde WhatsApp</p>
            </div>

            <div v-else class="space-y-1">
                <Link
                    v-for="order in orders.slice(0, 6)"
                    :key="order.id"
                    :href="`/chat?phone=${encodeURIComponent(order.phone_number)}`"
                    class="group flex items-center gap-3 rounded-xl border border-transparent px-2 py-2 transition hover:border-border/60 hover:bg-muted/30"
                >
                    <Avatar class="h-10 w-10 border border-border/60">
                        <AvatarFallback class="bg-muted text-xs font-semibold">
                            {{ getInitials(displayLabel(order)) }}
                        </AvatarFallback>
                    </Avatar>

                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <p class="truncate text-sm font-medium">{{ displayLabel(order) }}</p>
                            <span
                                class="inline-flex shrink-0 rounded-full px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide"
                                :class="statusBadge(order.status)"
                            >
                                {{ SALE_STATUS_LABELS[order.status] ?? order.status_label }}
                            </span>
                        </div>
                        <p class="truncate text-xs text-muted-foreground">
                            <span v-if="order.color">{{ order.product_name }} · {{ order.color }}</span>
                            <span v-else>{{ order.product_name }}</span>
                            <span v-if="order.created_at"> · {{ formatRelative(order.created_at) }}</span>
                        </p>
                    </div>

                    <div class="flex shrink-0 items-center gap-3">
                        <div class="text-right">
                            <p class="text-sm font-semibold tabular-nums">{{ formatMoney(order.total_amount) }}</p>
                            <p class="text-[10px] text-muted-foreground">{{ order.phone_number }}</p>
                        </div>
                        <span
                            class="h-2 w-2 rounded-full opacity-70"
                            :style="{ backgroundColor: saleStatusChartColor(order.status) }"
                        />
                        <MessageCircle class="h-4 w-4 text-emerald-500 opacity-0 transition group-hover:opacity-100" />
                    </div>
                </Link>
            </div>
        </CardContent>
    </Card>
</template>
