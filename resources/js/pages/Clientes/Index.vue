<script setup lang="ts">
import ModulePlaceholder from '@/components/ModulePlaceholder.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { Users } from 'lucide-vue-next';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Clientes', href: '/clientes' }];
</script>

<template>
    <Head title="Clientes" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <ModulePlaceholder
            title="Clientes"
            description="Directorio de contactos de WhatsApp con historial de compras y conversaciones."
            :icon="Users"
            :bullets="['Perfil por número de WhatsApp', 'Historial de pedidos', 'Notas del asesor']"
        />
    </AppLayout>
</template>
