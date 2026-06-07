<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Package, Plus } from 'lucide-vue-next';
import { Link } from '@inertiajs/vue3';

const props = defineProps<{
    searchQuery?: string;
}>();
</script>

<template>
    <div class="flex flex-col items-center justify-center py-16 text-center">
        <div class="rounded-full bg-muted p-4">
            <Package class="h-8 w-8 text-muted-foreground" />
        </div>
        <h3 class="mt-4 text-lg font-semibold">
            {{ searchQuery ? 'No se encontraron productos' : 'Sin productos registrados' }}
        </h3>
        <p class="mt-1 max-w-sm text-sm text-muted-foreground">
            {{ searchQuery
                ? `No hay resultados para "${searchQuery}". Intenta con otros términos.`
                : 'Comienza agregando tu primer producto al catálogo.'
            }}
        </p>
        <Button v-if="!searchQuery" class="mt-6 gap-2" as-child>
            <Link href="/productos/create">
                <Plus class="h-4 w-4" />
                Nuevo producto
            </Link>
        </Button>
    </div>
</template>
