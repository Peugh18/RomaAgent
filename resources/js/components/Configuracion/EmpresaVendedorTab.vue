<script setup lang="ts">
import { inject } from 'vue';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import CrmPanel from '@/components/crm/CrmPanel.vue';
import ConfigSectionHeader from '@/components/configuracion/ConfigSectionHeader.vue';
import { Building2 } from 'lucide-vue-next';
import type { CompanySettingsForm } from '@/types/settings';

const form = inject('companyConfigForm') as CompanySettingsForm & Record<string, unknown>;

if (!form) {
    throw new Error('companyConfigForm no está disponible');
}

const emit = defineEmits<{
    (e: 'subirLogo', event: Event): void
}>();

defineProps<{
    subiendoLogo: boolean
}>();

const manejarSubida = (e: Event) => {
    emit('subirLogo', e);
};
</script>

<template>
    <div class="space-y-6">
        <CrmPanel>
            <ConfigSectionHeader
                :icon="Building2"
                title="Datos de la Empresa y Vendedor"
                description="Información básica de tu negocio y del asesor"
            />
            <div class="space-y-4">
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="company_name">Nombre de la Empresa *</Label>
                        <Input id="company_name" v-model="form.company_name" placeholder="Tu Tienda" />
                    </div>
                    <div class="space-y-2">
                        <Label for="actividad">Actividad Económica *</Label>
                        <Input
                            id="actividad"
                            v-model="form.actividad_economica"
                            placeholder="Moda y Vestuario"
                        />
                    </div>
                    <div class="space-y-2">
                        <Label for="vendedor_nombre">Nombre del Vendedor (Asesor) *</Label>
                        <Input id="vendedor_nombre" v-model="form.vendedor_nombre" placeholder="Roma" />
                    </div>
                    <div class="space-y-2">
                        <Label for="vendedor_genero">Género del Vendedor (Asesor) *</Label>
                        <select
                            id="vendedor_genero"
                            v-model="form.vendedor_genero"
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        >
                            <option value="">-- Selecciona un género --</option>
                            <option value="Femenino">Femenino</option>
                            <option value="Masculino">Masculino</option>
                        </select>
                    </div>
                </div>

                <div class="space-y-2">
                    <Label for="descripcion_empresa">Descripción de la Empresa *</Label>
                    <Textarea
                        id="descripcion_empresa"
                        v-model="form.descripcion_empresa"
                        placeholder="Describe brevemente a qué se dedica tu empresa..."
                        :rows="3"
                    />
                </div>

                <div class="space-y-2">
                    <Label for="logo">Logo de la Empresa</Label>
                    <div class="flex items-center gap-4">
                        <div v-if="form.logo_path" class="h-20 w-20 overflow-hidden rounded-xl border border-border/60">
                            <img :src="form.logo_path" :alt="form.company_name" class="h-full w-full object-cover" />
                        </div>
                        <div class="flex-1">
                            <Input id="logo" type="file" accept="image/*" class="cursor-pointer" @change="manejarSubida" :disabled="subiendoLogo" />
                            <p class="text-xs text-muted-foreground mt-1">
                                {{ subiendoLogo ? 'Subiendo logo...' : 'PNG, JPG o GIF. Máximo 2MB' }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="pt-4 mt-2 border-t border-border/40">
                    <h4 class="mb-4 text-sm font-medium text-foreground">Redes Sociales y Sitio Web</h4>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="instagram">Instagram</Label>
                            <Input id="instagram" v-model="form.social_networks.instagram" placeholder="@tuempresa" />
                        </div>
                        <div class="space-y-2">
                            <Label for="facebook">Facebook</Label>
                            <Input id="facebook" v-model="form.social_networks.facebook" placeholder="facebook.com/tuempresa" />
                        </div>
                        <div class="space-y-2">
                            <Label for="tiktok">TikTok</Label>
                            <Input id="tiktok" v-model="form.social_networks.tiktok" placeholder="@tuempresa" />
                        </div>
                        <div class="space-y-2">
                            <Label for="website">Sitio Web</Label>
                            <Input id="website" v-model="form.social_networks.website" placeholder="https://miempresa.com" />
                        </div>
                    </div>
                </div>
            </div>
        </CrmPanel>
    </div>
</template>
