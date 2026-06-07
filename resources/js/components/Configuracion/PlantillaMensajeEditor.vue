<script setup lang="ts">
import { computed } from 'vue';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import {
    PLANTILLA_PEDIDO_CONFIRMADO_DEFECTO,
    PLANTILLA_VARIABLES,
    previsualizarPlantilla,
    tieneFormatoPlantillaIncorrecto,
} from '@/lib/plantillaMensaje';
import { AlertTriangle, Sparkles } from 'lucide-vue-next';

const model = defineModel<string>({ required: true });

withDefaults(
    defineProps<{
        id: string;
        label: string;
        placeholder?: string;
        rows?: number;
        showDefaultButton?: boolean;
    }>(),
    {
        placeholder: '',
        rows: 3,
        showDefaultButton: false,
    },
);

const formatoIncorrecto = computed(() => tieneFormatoPlantillaIncorrecto(model.value));
const preview = computed(() => previsualizarPlantilla(model.value));

const insertarVariable = (variable: string) => {
    model.value = model.value.trim() === '' ? variable : `${model.value} ${variable}`;
};

const usarPlantillaDefecto = () => {
    model.value = PLANTILLA_PEDIDO_CONFIRMADO_DEFECTO;
};
</script>

<template>
    <div class="space-y-3 rounded-lg border border-border bg-muted/20 p-4">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <Label :for="id" class="text-sm font-medium">{{ label }}</Label>
            <Button
                v-if="showDefaultButton"
                type="button"
                variant="outline"
                size="sm"
                class="h-7 gap-1 text-xs"
                @click="usarPlantillaDefecto"
            >
                <Sparkles class="h-3.5 w-3.5" />
                Usar plantilla recomendada
            </Button>
        </div>

        <div class="flex flex-wrap gap-1.5">
            <button
                v-for="variable in PLANTILLA_VARIABLES"
                :key="variable.key"
                type="button"
                class="rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-0.5 text-[11px] font-medium text-emerald-800 transition hover:bg-emerald-100 dark:border-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200"
                :title="variable.label"
                @click="insertarVariable(variable.key)"
            >
                {{ variable.key }}
            </button>
        </div>

        <Textarea
            :id="id"
            v-model="model"
            :placeholder="placeholder"
            :rows="rows"
            class="resize-none font-mono text-sm"
        />

        <p v-if="formatoIncorrecto" class="flex items-start gap-2 text-xs text-amber-700 dark:text-amber-400">
            <AlertTriangle class="mt-0.5 h-3.5 w-3.5 shrink-0" />
            Formato incorrecto: no uses <code class="rounded bg-amber-100 px-1 dark:bg-amber-950">{"valor"}</code>.
            Usa las variables de arriba, por ejemplo <code class="rounded bg-amber-100 px-1 dark:bg-amber-950">{producto}</code>.
        </p>

        <div
            v-else-if="preview"
            class="rounded-md border border-dashed border-emerald-300/60 bg-emerald-50/50 px-3 py-2 dark:border-emerald-800 dark:bg-emerald-950/20"
        >
            <p class="text-[10px] font-semibold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">
                Vista previa (ejemplo)
            </p>
            <p class="mt-1 text-sm text-foreground">{{ preview }}</p>
        </div>
    </div>
</template>
