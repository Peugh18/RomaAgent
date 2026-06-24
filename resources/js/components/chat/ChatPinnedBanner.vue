<script setup lang="ts">
import type { ChatMessage } from '@/types/chat';
import { Pin, X } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    message: ChatMessage | null;
}>();

const emit = defineEmits<{
    'go-to-message': [id: number];
    unpin: [id: number];
}>();

const preview = computed(() => {
    if (!props.message) return '';
    const content = props.message.content ?? '';
    return content.length > 80 ? content.slice(0, 80) + '...' : content;
});
</script>

<template>
    <div
        v-if="message"
        class="flex items-center gap-2 border-b border-border bg-emerald-50/80 px-3 py-1.5 dark:bg-emerald-950/20"
    >
        <Pin class="h-3.5 w-3.5 shrink-0 text-emerald-600 dark:text-emerald-400" />
        <button
            type="button"
            class="min-w-0 flex-1 text-left"
            @click="emit('go-to-message', message!.id)"
        >
            <p class="text-[10px] font-semibold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">
                Mensaje fijado
            </p>
            <p class="truncate text-xs text-muted-foreground">{{ preview }}</p>
        </button>
        <button
            type="button"
            class="shrink-0 rounded-full p-0.5 text-muted-foreground transition hover:bg-muted hover:text-foreground"
            title="Desfijar"
            @click.stop="emit('unpin', message!.id)"
        >
            <X class="h-3.5 w-3.5" />
        </button>
    </div>
</template>
