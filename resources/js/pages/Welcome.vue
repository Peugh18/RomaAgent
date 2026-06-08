<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Head, Link } from '@inertiajs/vue3';
import { MessageSquare } from 'lucide-vue-next';
</script>

<template>
    <Head title="RomaAgent" />

    <div class="flex min-h-screen flex-col items-center justify-center bg-background p-6">
        <div class="w-full max-w-md space-y-6 text-center">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                <MessageSquare class="h-7 w-7" />
            </div>

            <div class="space-y-2">
                <h1 class="text-2xl font-semibold tracking-tight">RomaAgent</h1>
                <p class="text-sm text-muted-foreground">
                    Agente CRM de WhatsApp. Gestiona conversaciones entrantes y responde desde un solo lugar.
                </p>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row sm:justify-center">
                <Button v-if="$page.props.auth.user" as-child>
                    <Link :href="route('chat.index')">Ir al chat</Link>
                </Button>
                <template v-else>
                    <Button as-child>
                        <Link :href="route('login')">Iniciar sesión</Link>
                    </Button>
                </template>
            </div>
        </div>
    </div>
</template>
