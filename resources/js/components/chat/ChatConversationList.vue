<script setup lang="ts">
import { formatChatTime } from '@/lib/chatFormatting';
import { getInitials } from '@/composables/useInitials';
import type { ChatConversation } from '@/types/chat';
import { Bot, Clock, Loader2, MessageSquare, UserRound, CreditCard } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    conversations: ChatConversation[];
    selectedPhone: string | null;
    loading: boolean;
}>();

const emit = defineEmits<{
    select: [phone: string];
}>();

const activeFilter = ref<'todos' | 'asesor' | 'pagos'>('todos');

const filters = [
    { id: 'todos' as const, label: 'Todos' },
    { id: 'pagos' as const, label: 'Pagos' },
    { id: 'asesor' as const, label: 'Humano' },
];

const filteredConversations = computed(() => {
    if (activeFilter.value === 'pagos') {
        return props.conversations.filter((c) => c.pending_payment);
    }
    if (activeFilter.value === 'asesor') {
        return props.conversations.filter((c) => c.ia_paused && !c.pending_payment);
    }
    return props.conversations;
});

const pendingCount = computed(() => props.conversations.filter((c) => c.pending_payment).length);
const humanCount = computed(() => props.conversations.filter((c) => c.ia_paused && !c.pending_payment).length);
</script>

<template>
    <aside class="flex w-full shrink-0 flex-col overflow-hidden rounded-xl border border-border bg-card shadow-sm lg:w-[22rem]">
        <div class="space-y-3 border-b border-border bg-muted/30 p-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2 text-sm font-semibold">
                    <MessageSquare class="h-4 w-4 text-emerald-600" />
                    Chat
                </div>
                <div class="flex items-center gap-1">
                    <span
                        v-if="humanCount > 0"
                        class="rounded-full bg-violet-100 px-2 py-0.5 text-[10px] font-semibold text-violet-800 dark:bg-violet-950 dark:text-violet-200"
                    >
                        {{ humanCount }} humano
                    </span>
                    <span
                        v-if="pendingCount > 0"
                        class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-800 dark:bg-amber-950 dark:text-amber-200"
                    >
                        {{ pendingCount }} pago(s)
                    </span>
                </div>
            </div>

            <div class="flex gap-1 rounded-lg bg-background p-1">
                <button
                    v-for="filter in filters"
                    :key="filter.id"
                    type="button"
                    class="flex-1 rounded-md px-2 py-1.5 text-xs font-medium transition"
                    :class="
                        activeFilter === filter.id
                            ? 'bg-emerald-600 text-white shadow-sm'
                            : 'text-muted-foreground hover:bg-muted'
                    "
                    @click="activeFilter = filter.id"
                >
                    {{ filter.label }}
                </button>
            </div>
        </div>

        <div class="border-b border-border px-4 py-2">
            <p class="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
                Conversaciones ({{ filteredConversations.length }})
            </p>
        </div>

        <div class="flex-1 overflow-y-auto">
            <div v-if="loading && conversations.length === 0" class="flex justify-center py-10">
                <Loader2 class="h-5 w-5 animate-spin text-muted-foreground" />
            </div>

            <button
                v-for="conversation in filteredConversations"
                :key="conversation.phone"
                type="button"
                class="relative flex w-full gap-3 border-b border-border/60 px-4 py-3 text-left transition hover:bg-muted/40"
                :class="selectedPhone === conversation.phone ? 'bg-emerald-50/80 dark:bg-emerald-950/20' : ''"
                @click="emit('select', conversation.phone)"
            >
                <span
                    v-if="selectedPhone === conversation.phone"
                    class="absolute bottom-0 left-0 top-0 w-1 rounded-r bg-emerald-500"
                />

                <div
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-emerald-400 to-teal-600 text-sm font-semibold text-white"
                >
                    {{ getInitials(conversation.name || conversation.phone) }}
                </div>

                <div class="min-w-0 flex-1">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold">
                                {{ conversation.name || conversation.phone }}
                            </p>
                            <p class="truncate text-[11px] text-muted-foreground">{{ conversation.phone }}</p>
                        </div>
                        <span class="flex shrink-0 items-center gap-0.5 text-[10px] text-muted-foreground">
                            <Clock class="h-3 w-3" />
                            {{ formatChatTime(conversation.last_at) }}
                        </span>
                    </div>
                    <p class="mt-1 truncate text-xs text-muted-foreground">{{ conversation.last_message }}</p>
                    <div class="mt-2 flex flex-wrap gap-1">
                        <span
                            v-if="conversation.pending_payment"
                            class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-medium text-amber-800 dark:bg-amber-950 dark:text-amber-200"
                        >
                            <CreditCard class="h-3 w-3" />
                            Pago por revisar
                        </span>
                        <span
                            v-else-if="conversation.ia_paused"
                            class="inline-flex items-center gap-1 rounded-full bg-violet-100 px-2 py-0.5 text-[10px] font-medium text-violet-700 dark:bg-violet-950 dark:text-violet-300"
                        >
                            <UserRound class="h-3 w-3" />
                            Humano
                        </span>
                        <span
                            v-else
                            class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-medium text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300"
                        >
                            <Bot class="h-3 w-3" />
                            IA activa
                        </span>
                    </div>
                </div>
            </button>

            <p v-if="!loading && filteredConversations.length === 0" class="p-4 text-sm text-muted-foreground">
                No hay conversaciones en este filtro.
            </p>
        </div>
    </aside>
</template>
