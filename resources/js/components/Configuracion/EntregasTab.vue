<script setup lang="ts">
import { inject } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import { Clock, Truck, ExternalLink } from 'lucide-vue-next';
import type { CompanySettingsForm } from '@/types/settings';

const form = inject('companyConfigForm') as CompanySettingsForm & Record<string, unknown>;

if (!form) {
    throw new Error('companyConfigForm no está disponible');
}
</script>

<template>
    <div class="space-y-6">
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <Clock class="h-5 w-5" />
                    Horarios de Entrega
                </CardTitle>
                <CardDescription>Cuándo se realizan las entregas según el método</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="horario-motorizado-entregas">Horario Motorizado</Label>
                        <Input
                            id="horario-motorizado-entregas"
                            v-model="form.horario_entregas"
                            placeholder="Ej: Lunes a Sábado 5pm-9pm"
                        />
                    </div>

                    <div class="space-y-2">
                        <Label for="horario-shalom-entregas">Horario Shalom</Label>
                        <Input
                            id="horario-shalom-entregas"
                            v-model="form.horario_shalom"
                            placeholder="Ej: Lunes, Miércoles, Viernes"
                        />
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <Truck class="h-5 w-5" />
                    Tarifario de Entregas
                </CardTitle>
                <CardDescription>Los costos por distrito se gestionan en Zonas de Entrega</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-950">
                    <p class="text-sm text-blue-900 dark:text-blue-200">
                        El tarifario de motorizado y Shalom se carga automáticamente desde
                        <strong>Zonas de Entrega</strong> y se incluye en el prompt de la IA.
                    </p>
                </div>
                <Button as-child variant="outline" class="gap-2">
                    <Link href="/zonas-delivery">
                        <ExternalLink class="h-4 w-4" />
                        Ir a Zonas de Entrega
                    </Link>
                </Button>
            </CardContent>
        </Card>
    </div>
</template>
