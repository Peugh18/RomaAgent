<script setup lang="ts">
import { chatStatusLabel, formatChatTime } from '@/lib/chatFormatting';
import { isMediaOnlyLabel, isStickerMessage, mediaKind, mediaSource, mediaUnavailable } from '@/composables/useChatMedia';
import type { ChatMessage } from '@/types/chat';
import { ExternalLink, MapPin, RotateCw } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    message: ChatMessage;
    resending?: boolean;
}>();

const emit = defineEmits<{
    resend: [message: ChatMessage];
}>();

const canResend = computed(
    () => props.message.direction === 'outgoing' && props.message.status === 'failed',
);

const isSticker = computed(() => isStickerMessage(props.message));

const isLocationMessage = computed(
    () =>
        props.message.content.includes('Ubicación compartida') ||
        props.message.metadata?.type === 'location',
);

const locationMapsUrl = computed(() => {
    const metadata = props.message.metadata;
    if (!metadata) {
        return null;
    }

    if (typeof metadata.maps_url === 'string' && metadata.maps_url !== '') {
        return metadata.maps_url;
    }

    const latitude = metadata.latitude;
    const longitude = metadata.longitude;

    if (typeof latitude === 'number' && typeof longitude === 'number') {
        return `https://www.google.com/maps?q=${latitude},${longitude}`;
    }

    return null;
});

const locationLabel = computed(() => {
    const metadata = props.message.metadata;
    const address = typeof metadata?.location_address === 'string' ? metadata.location_address : null;
    const name = typeof metadata?.location_name === 'string' ? metadata.location_name : null;

    if (address) {
        return address;
    }

    if (name) {
        return name;
    }

    return props.message.content || '📍 Ubicación compartida';
});
</script>

<template>
    <div class="flex" :class="message.direction === 'outgoing' ? 'justify-end' : 'justify-start'">
        <div
            class="max-w-[92%] sm:max-w-[min(85%,28rem)] text-sm"
            :class="
                isSticker
                    ? 'px-0 py-0 shadow-none'
                    : 'rounded-2xl px-3 py-1.5 shadow-sm ' +
                      (message.direction === 'outgoing'
                          ? 'rounded-br-md bg-[#d9fdd3] text-[#111b21] dark:bg-emerald-900/50 dark:text-emerald-50'
                          : 'rounded-bl-md border border-border bg-white text-foreground dark:bg-card')
            "
        >
            <div
                v-if="isLocationMessage"
                class="space-y-2 rounded-lg bg-muted/60 px-3 py-2 text-sm"
            >
                <div class="flex items-start gap-2">
                    <MapPin class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" />
                    <div class="min-w-0">
                        <p class="font-medium">Ubicación actual</p>
                        <p class="break-words text-xs text-muted-foreground">{{ locationLabel }}</p>
                    </div>
                </div>
                <a
                    v-if="locationMapsUrl"
                    :href="locationMapsUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center gap-1.5 text-xs font-medium text-emerald-700 underline underline-offset-2 hover:text-emerald-800 dark:text-emerald-300"
                >
                    Ver en Google Maps
                    <ExternalLink class="h-3 w-3" />
                </a>
            </div>

            <template v-else>
                <p
                    v-if="
                        message.content &&
                        (!isMediaOnlyLabel(message.content) || !mediaSource(message)) &&
                        !isSticker
                    "
                    class="whitespace-pre-wrap break-words leading-relaxed"
                >
                    {{ message.content }}
                </p>
                <div v-if="isSticker && mediaSource(message)" class="inline-flex flex-col items-start gap-1">
                    <img
                        :src="mediaSource(message)!"
                        alt="Sticker"
                        class="max-h-28 max-w-28 object-contain"
                    />
                    <span class="text-xs text-muted-foreground">Sticker</span>
                </div>
                <img
                    v-else-if="mediaKind(message) === 'image' && mediaSource(message)"
                    :src="mediaSource(message)!"
                    :alt="message.content"
                    class="mt-1 max-h-64 rounded-lg object-contain"
                />
                <audio
                    v-if="mediaKind(message) === 'audio' && mediaSource(message)"
                    :src="mediaSource(message)!"
                    controls
                    class="mt-2 max-w-full"
                />
                <video
                    v-if="mediaKind(message) === 'video' && mediaSource(message)"
                    :src="mediaSource(message)!"
                    controls
                    class="mt-2 max-h-64 max-w-full rounded-lg"
                />
                <p v-if="mediaUnavailable(message)" class="mt-1 text-xs text-amber-600 dark:text-amber-400">
                    No se pudo cargar el archivo. Pide a la clienta que lo reenvíe.
                </p>
            </template>

            <div
                class="mt-1.5 flex items-center justify-end gap-2 text-xs"
                :class="message.direction === 'outgoing' ? 'text-emerald-900/60 dark:text-emerald-100/90' : 'text-muted-foreground'"
            >
                <span>{{ formatChatTime(message.created_at) }}</span>
                <span v-if="message.direction === 'outgoing'">{{ chatStatusLabel(message.status) }}</span>
            </div>

            <p
                v-if="message.metadata?.send_error"
                class="mt-1 text-xs"
                :class="message.direction === 'outgoing' ? 'text-red-700 dark:text-red-300' : 'text-red-600 dark:text-red-400'"
            >
                {{ message.metadata.send_error }}
            </p>

            <button
                v-if="canResend"
                type="button"
                class="mt-2 inline-flex items-center gap-1.5 rounded-md border border-red-300/80 bg-white/80 px-2.5 py-1 text-xs font-medium text-red-700 transition hover:bg-red-50 disabled:opacity-60 dark:border-red-800 dark:bg-red-950/40 dark:text-red-200 dark:hover:bg-red-950/70"
                :disabled="resending"
                @click="emit('resend', message)"
            >
                <RotateCw class="h-3 w-3" :class="{ 'animate-spin': resending }" />
                {{ resending ? 'Reenviando…' : 'Reenviar' }}
            </button>
        </div>
    </div>
</template>
