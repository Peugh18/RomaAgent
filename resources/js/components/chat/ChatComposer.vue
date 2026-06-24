<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Loader2, Mic, Paperclip, Send, Smile, X } from 'lucide-vue-next';
import { ref, onMounted, onUnmounted } from 'vue';
import EmojiPicker from 'vue3-emoji-picker';
import 'vue3-emoji-picker/css';

const model = defineModel<string>({ required: true });

const props = defineProps<{
    disabled?: boolean;
    sending?: boolean;
    imagePreviewUrl?: string | null;
}>();

const emit = defineEmits<{
    submit: [];
    'image-selected': [file: File | null];
    'clear-image': [];
}>();

const fileInput = ref<HTMLInputElement | null>(null);
const showEmojiPicker = ref(false);
const composerRef = ref<HTMLElement | null>(null);

const onSelectEmoji = (emoji: any) => {
    model.value += emoji.i;
};

const handleFileChange = (e: Event) => {
    const target = e.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
        emit('image-selected', target.files[0]);
    }
    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

const handleClickOutside = (e: MouseEvent) => {
    if (showEmojiPicker.value && composerRef.value && !composerRef.value.contains(e.target as Node)) {
        showEmojiPicker.value = false;
    }
};

const cannedResponses = [
    { id: 'banco', label: 'Cuentas Bancarias', text: 'Nuestras cuentas bancarias son:\nBCP: 191-12345678-0-99 (RomaAgent SAC)\nYape/Plin: 987654321\nPor favor envíanos la captura del comprobante por aquí.' },
    { id: 'delivery', label: 'Costo de envío', text: 'El costo de envío es de S/ 10 para Lima Metropolitana (24 a 48 hrs) y S/ 15 para provincias (Olva Courier, 2 a 4 días hábiles).' },
    { id: 'horario', label: 'Horarios', text: 'Nuestro horario de atención es de Lunes a Sábado de 9:00 AM a 6:00 PM.' },
    { id: 'direccion', label: 'Ubicación', text: 'Nos encontramos ubicados en Av. Principal 123, tienda 4, Lima.' },
];

const selectCannedResponse = (text: string) => {
    model.value = text;
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});
</script>

<template>
    <footer ref="composerRef" class="relative border-t border-border bg-[#f0f2f5] p-2 dark:bg-muted/30">
        <!-- Vista previa de imagen -->
        <div v-if="imagePreviewUrl" class="mb-3 flex items-start gap-2 rounded-xl bg-muted/40 p-2">
            <div class="relative h-20 w-20 shrink-0 overflow-hidden rounded-md border border-border bg-background">
                <img :src="imagePreviewUrl" class="h-full w-full object-cover" />
                <button
                    type="button"
                    class="absolute right-1 top-1 flex h-5 w-5 items-center justify-center rounded-full bg-black/60 text-white transition hover:bg-red-500"
                    @click="emit('clear-image')"
                >
                    <X class="h-3.5 w-3.5" />
                </button>
            </div>
            <div class="flex-1 py-1 text-sm text-muted-foreground">
                <p>Imagen adjunta lista para enviar.</p>
            </div>
        </div>

        <!-- Popover nativo de emojis -->
        <div
            v-if="showEmojiPicker"
            class="absolute bottom-16 left-3 z-50 shadow-xl"
        >
            <EmojiPicker :native="true" theme="auto" @select="onSelectEmoji" />
        </div>

        <!-- Botones Rápidos (Píldoras) -->
        <div class="mb-1.5 flex gap-1.5 overflow-x-auto pb-1 scrollbar-hide">
            <button
                v-for="response in cannedResponses"
                :key="response.id"
                type="button"
                class="shrink-0 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-0.5 text-[11px] font-medium text-emerald-700 transition hover:bg-emerald-100 hover:text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-950/30 dark:text-emerald-300 dark:hover:bg-emerald-900/50"
                @click="selectCannedResponse(response.text)"
            >
                {{ response.label }}
            </button>
        </div>

        <form class="flex items-end gap-2" @submit.prevent="emit('submit'); showEmojiPicker = false;">
            <input
                ref="fileInput"
                type="file"
                accept="image/jpeg,image/png,image/webp"
                class="hidden"
                @change="handleFileChange"
            />
            
            <div class="flex gap-1">
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="h-8 w-8 shrink-0 text-muted-foreground transition hover:text-foreground"
                    :disabled="disabled || sending"
                    @click="showEmojiPicker = !showEmojiPicker"
                >
                    <Smile class="h-5 w-5" />
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="h-8 w-8 shrink-0 text-muted-foreground transition hover:text-foreground"
                    :disabled="disabled || sending"
                    @click="fileInput?.click()"
                >
                    <Paperclip class="h-5 w-5" />
                </Button>
            </div>

            <Input
                v-model="model"
                placeholder="Escribe un mensaje…"
                class="min-h-9 flex-1 rounded-2xl border-0 bg-white px-3 shadow-sm dark:bg-background"
                :disabled="disabled || sending"
            />

            <Button
                type="submit"
                size="icon"
                class="h-10 w-10 shrink-0 rounded-full bg-emerald-600 hover:bg-emerald-700"
                :disabled="disabled || sending || (!model.trim() && !imagePreviewUrl)"
            >
                <Loader2 v-if="sending" class="h-4 w-4 animate-spin" />
                <Send v-else-if="model.trim() || imagePreviewUrl" class="h-4 w-4" />
                <Mic v-else class="h-4 w-4" />
            </Button>
        </form>
    </footer>
</template>
