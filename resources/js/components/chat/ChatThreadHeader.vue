<script setup lang="ts">
import { apiJson, getCsrfToken } from '@/composables/useApi';
import ChatLabelManager from './ChatLabelManager.vue';
import { Bot, Sparkles, UserRound, Loader2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = defineProps<{
    name: string | null;
    phone: string | null;
}>();

const chatMode = ref<'bot' | 'human'>('bot');
const toggling = ref(false);

const emit = defineEmits<{
    'mode-change': [mode: 'bot' | 'human'];
    'labels-updated': [labels: any[]];
    'open-sale-panel': [];
    'back': [];
}>();

const loadMode = async () => {
    if (!props.phone) {
        return;
    }

    try {
        const encoded = encodeURIComponent(props.phone);
        const customer = await apiJson<{ ia_paused: boolean }>(`/api/customers/${encoded}`);
        chatMode.value = customer?.ia_paused ? 'human' : 'bot';
        emit('mode-change', chatMode.value);
    } catch {
        chatMode.value = 'bot';
        emit('mode-change', chatMode.value);
    }
};

const setMode = async (mode: 'bot' | 'human') => {
    if (!props.phone || toggling.value) {
        return;
    }

    toggling.value = true;
    chatMode.value = mode;
    emit('mode-change', mode);

    try {
        const encoded = encodeURIComponent(props.phone);
        await apiJson(`/api/customers/${encoded}/ia-mode`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify({
                ia_paused: mode === 'human',
                reason: mode === 'human' ? 'Control manual desde panel' : null,
            }),
        });
    } catch {
        await loadMode();
    } finally {
        toggling.value = false;
    }
};

watch(() => props.phone, () => {
    void loadMode();
}, { immediate: true });
</script>

<template>
    <header class="flex flex-wrap items-center justify-between gap-3 border-b border-border bg-card px-4 py-3">
        <div class="flex min-w-0 items-center gap-2">
            <button
                v-if="phone"
                type="button"
                class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md border border-transparent bg-transparent text-muted-foreground transition hover:bg-muted lg:hidden"
                @click="$emit('back')"
            >
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
            </button>
            <div class="min-w-0">
                <h2 v-if="phone" class="truncate text-base font-semibold">{{ name || phone }}</h2>
                <h2 v-else class="text-base font-semibold text-muted-foreground">Sin conversación</h2>
                <p v-if="phone" class="text-xs text-muted-foreground">{{ phone }}</p>
            </div>
        </div>

        <div v-if="phone" class="flex flex-wrap items-center gap-3">
            <button
                type="button"
                class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-border bg-background text-muted-foreground shadow-sm transition hover:bg-muted lg:hidden"
                @click="$emit('open-sale-panel')"
            >
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M15 3v18"/><path d="m10 15-3-3 3-3"/></svg>
            </button>

            <div class="flex items-center gap-2">
                <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Modo</span>
                <div class="flex rounded-lg border border-border bg-muted/40 p-0.5">
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium transition disabled:opacity-50"
                        :class="
                            chatMode === 'bot'
                                ? 'bg-emerald-600 text-white shadow-sm'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                        :disabled="toggling"
                        @click="setMode('bot')"
                    >
                        <Sparkles class="h-3.5 w-3.5" />
                        Bot IA
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium transition disabled:opacity-50"
                        :class="
                            chatMode === 'human'
                                ? 'bg-violet-600 text-white shadow-sm'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                        :disabled="toggling"
                        @click="setMode('human')"
                    >
                        <UserRound class="h-3.5 w-3.5" />
                        Humano
                    </button>
                </div>
            </div>
            
            <ChatLabelManager 
                :phone="phone" 
                @labels-updated="$emit('labels-updated', $event)" 
            />

            <span
                class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium"
                :class="
                    chatMode === 'bot'
                        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'
                        : 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300'
                "
            >
                <Loader2 v-if="toggling" class="h-3.5 w-3.5 animate-spin" />
                <Bot v-else-if="chatMode === 'bot'" class="h-3.5 w-3.5" />
                <UserRound v-else class="h-3.5 w-3.5" />
                {{ chatMode === 'bot' ? 'IA activa' : 'Asesor humano' }}
            </span>
        </div>
    </header>
</template>
