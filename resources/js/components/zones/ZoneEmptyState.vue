<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { MapPin, Plus } from 'lucide-vue-next';

const props = defineProps<{
    searchQuery?: string;
}>();

const emit = defineEmits<{
    create: [];
}>();
</script>

<template>
    <div class="flex flex-col items-center justify-center py-16 text-center">
        <div class="rounded-full bg-muted p-4">
            <MapPin class="h-8 w-8 text-muted-foreground" />
        </div>
        <h3 class="mt-4 text-lg font-semibold">
            {{ searchQuery ? 'No se encontraron zonas' : 'Sin zonas de delivery' }}
        </h3>
        <p class="mt-1 max-w-sm text-sm text-muted-foreground">
            {{ searchQuery
                ? `No hay resultados para "${searchQuery}". Intenta con otros términos.`
                : 'Configura las tarifas de delivery para cada distrito. La IA usará esta información.'
            }}
        </p>
        <Button v-if="!searchQuery" class="mt-6 gap-2" @click="emit('create')">
            <Plus class="h-4 w-4" />
            Nueva zona
        </Button>
    </div>
</template>
