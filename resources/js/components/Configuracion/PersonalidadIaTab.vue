<script setup lang="ts">
import { inject } from 'vue';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import CrmPanel from '@/components/crm/CrmPanel.vue';
import ConfigSectionHeader from '@/components/configuracion/ConfigSectionHeader.vue';
import { MessageSquare, Bot, Key, Sliders } from 'lucide-vue-next';
import type { CompanySettingsForm } from '@/types/settings';

const form = inject('companyConfigForm') as CompanySettingsForm & Record<string, unknown>;

if (!form) {
    throw new Error('companyConfigForm no está disponible');
}
</script>

<template>
    <div class="space-y-6">
        <CrmPanel>
            <ConfigSectionHeader
                :icon="MessageSquare"
                title="Identidad y personalidad"
                description="Quién es el bot, cómo habla y cómo trata al cliente. Va directo al prompt de la IA."
            />
            <div class="space-y-4">
                <div class="space-y-2">
                    <Label for="personalidad_bot">Personalidad del bot *</Label>
                    <Textarea
                        id="personalidad_bot"
                        v-model="form.personalidad_bot"
                        placeholder="Ej: Eres una asesora de ventas de moda femenina. Tu trato es amable, cercano, elegante y profesional. Hablas en femenino, de forma natural..."
                        :rows="6"
                    />
                    <p class="text-xs text-muted-foreground">
                        Sé específico: rol, trato, vocabulario, palabras que sí/no usar.
                    </p>
                </div>

                <div class="space-y-2">
                    <Label for="estilo_ventas">Estilo de Ventas (Estrategias y cierre) *</Label>
                    <Textarea
                        id="estilo_ventas"
                        v-model="form.estilo_ventas"
                        placeholder="Ej: Ofrece siempre promociones sutiles. Procura cerrar la venta solicitando la dirección de entrega de forma amable pero asertiva..."
                        :rows="4"
                    />
                    <p class="text-xs text-muted-foreground">
                        Describe cómo quieres que el bot venda, maneje objeciones o cierre tratos.
                    </p>
                </div>

                <div class="space-y-2">
                    <Label for="respuesta_si_es_bot">Si preguntan si eres bot (Opcional)</Label>
                    <Textarea
                        id="respuesta_si_es_bot"
                        v-model="form.respuesta_si_es_bot"
                        placeholder="Ej: Soy asesora de Roma Store, bella. Te estoy ayudando por aquí para que sea más rápido, pero si prefieres, me avisas y te derivo con alguien más del equipo."
                        :rows="3"
                    />
                </div>
            </div>
        </CrmPanel>

        <CrmPanel>
            <ConfigSectionHeader
                :icon="Bot"
                title="Agente de ventas IA"
                description="Configura Gemini para respuestas automáticas en WhatsApp"
            />
            <div class="space-y-6">
                <div class="flex items-start gap-3">
                    <Checkbox
                        id="agente-activado"
                        v-model:checked="form.configuracion_agente!.agente_ia_activado"
                    />
                    <div class="space-y-0.5">
                        <Label for="agente-activado">Activar Agente IA</Label>
                        <p class="text-xs text-muted-foreground">Responde automáticamente mensajes entrantes.</p>
                    </div>
                </div>

                <div v-if="form.configuracion_agente?.agente_ia_activado" class="space-y-4 border-t pt-4">
                    <div class="space-y-2">
                        <Label for="modelo-ia">Modelo</Label>
                        <select
                            id="modelo-ia"
                            v-model="form.configuracion_agente!.agente_ia_modelo"
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        >
                            <option
                                v-for="(label, value) in form.configuracion_agente?.modelos_disponibles || {}"
                                :key="value"
                                :value="value"
                            >
                                {{ label }}
                            </option>
                        </select>
                    </div>

                    <div class="space-y-2">
                        <Label for="api-key" class="flex items-center gap-2">
                            <Key class="h-4 w-4" />
                            API Key (Google AI Studio)
                        </Label>
                        <Input
                            id="api-key"
                            v-model="form.configuracion_agente!.agente_ia_api_key"
                            type="password"
                            placeholder="AIzaSy..."
                            autocomplete="off"
                        />
                        <p v-if="form.configuracion_agente?.api_key_configurada" class="text-xs text-emerald-600">
                            API Key configurada. Deja en blanco para mantener la actual.
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="temperatura" class="flex items-center gap-2">
                            <Sliders class="h-4 w-4" />
                            Creatividad: {{ form.configuracion_agente!.agente_ia_temperatura }}
                        </Label>
                        <input
                            id="temperatura"
                            v-model.number="form.configuracion_agente!.agente_ia_temperatura"
                            type="range"
                            min="0"
                            max="1"
                            step="0.1"
                            class="h-2 w-full"
                        />
                    </div>
                </div>
            </div>
        </CrmPanel>
    </div>
</template>
