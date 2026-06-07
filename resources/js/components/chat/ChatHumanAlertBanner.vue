<script setup lang="ts">
import type { ChatConversation } from '@/types/chat';
import { AlertTriangle, X } from 'lucide-vue-next';

defineProps<{
    visible: boolean;
    chats: ChatConversation[];
}>();

const emit = defineEmits<{
    dismiss: [];
    select: [phone: string];
}>();
</script>

<template>
    <div
        v-if="visible && chats.length > 0"
        class="flex items-start gap-3 border-b border-violet-300 bg-violet-100 px-4 py-3 text-violet-950 dark:border-violet-800 dark:bg-violet-950/50 dark:text-violet-100"
        role="alert"
    >
        <AlertTriangle class="mt-0.5 h-5 w-5 shrink-0 text-violet-700 dark:text-violet-300" />

        <div class="min-w-0 flex-1">
            <p class="text-sm font-semibold">
                {{ chats.length === 1 ? '1 chat necesita atención humana' : `${chats.length} chats necesitan atención humana` }}
            </p>
            <ul class="mt-1 space-y-1">
                <li v-for="chat in chats.slice(0, 3)" :key="chat.phone">
                    <button
                        type="button"
                        class="truncate text-left text-xs underline decoration-violet-500/60 underline-offset-2 hover:decoration-violet-700"
                        @click="emit('select', chat.phone)"
                    >
                        {{ chat.name || chat.phone }}
                        <span v-if="chat.ia_pause_reason" class="text-violet-700/80 dark:text-violet-300/80">
                            — {{ chat.ia_pause_reason }}
                        </span>
                    </button>
                </li>
            </ul>
            <p v-if="chats.length > 3" class="mt-1 text-[11px] text-violet-800/80 dark:text-violet-200/80">
                y {{ chats.length - 3 }} más…
            </p>
        </div>

        <button
            type="button"
            class="rounded-md p-1 text-violet-700 transition hover:bg-violet-200/80 dark:text-violet-300 dark:hover:bg-violet-900/60"
            aria-label="Ocultar alerta"
            @click="emit('dismiss')"
        >
            <X class="h-4 w-4" />
        </button>
    </div>
</template>
