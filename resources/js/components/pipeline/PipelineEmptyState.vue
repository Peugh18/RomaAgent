<script setup lang="ts">
import { Card, CardContent } from '@/components/ui/card';
import { Kanban } from 'lucide-vue-next';
</script>

<template>
    <Card class="border-dashed">
        <CardContent class="flex flex-col items-center justify-center py-16 text-center">
            <div class="rounded-full bg-muted p-4">
                <Kanban class="h-8 w-8 text-muted-foreground" />
            </div>
            <h3 class="mt-4 text-lg font-semibold">Pipeline vacío</h3>
            <p class="mt-1 max-w-md text-sm text-muted-foreground">
                Aún no hay pedidos registrados. La IA creará pedidos automáticamente cuando los clientes conversen por WhatsApp.
            </p>
        </CardContent>
    </Card>
</template>
