<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Loader2, Mic, Paperclip, Send, Smile } from 'lucide-vue-next';

const model = defineModel<string>({ required: true });

defineProps<{
    disabled?: boolean;
    sending?: boolean;
}>();

const emit = defineEmits<{
    submit: [];
}>();
</script>

<template>
    <footer class="border-t border-border bg-[#f0f2f5] p-3 dark:bg-muted/30">
        <form class="flex items-end gap-2" @submit.prevent="emit('submit')">
            <div class="flex gap-1">
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="h-9 w-9 shrink-0 text-muted-foreground"
                    disabled
                >
                    <Smile class="h-5 w-5" />
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="h-9 w-9 shrink-0 text-muted-foreground"
                    disabled
                >
                    <Paperclip class="h-5 w-5" />
                </Button>
            </div>

            <Input
                v-model="model"
                placeholder="Escribe un mensaje…"
                class="min-h-10 flex-1 rounded-2xl border-0 bg-white shadow-sm dark:bg-background"
                :disabled="disabled || sending"
            />

            <Button
                type="submit"
                size="icon"
                class="h-10 w-10 shrink-0 rounded-full bg-emerald-600 hover:bg-emerald-700"
                :disabled="disabled || sending || !model.trim()"
            >
                <Loader2 v-if="sending" class="h-4 w-4 animate-spin" />
                <Send v-else-if="model.trim()" class="h-4 w-4" />
                <Mic v-else class="h-4 w-4" />
            </Button>
        </form>
    </footer>
</template>
