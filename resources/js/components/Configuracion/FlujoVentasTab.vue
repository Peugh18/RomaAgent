<script setup lang="ts">
import { computed, inject } from 'vue';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { MessageSquare, Zap, Users, AlertCircle, Info } from 'lucide-vue-next';
import type { CompanySettingsForm } from '@/types/settings';
import PlantillaMensajeEditor from '@/components/Configuracion/PlantillaMensajeEditor.vue';

const form = inject('companyConfigForm') as CompanySettingsForm & Record<string, unknown>;

if (!form) {
    throw new Error('companyConfigForm no está disponible');
}


</script>

<template>
    <div class="space-y-6">
        <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-950">
            <div class="flex gap-3">
                <Info class="h-5 w-5 shrink-0 text-blue-600 dark:text-blue-400" />
                <div class="space-y-2 text-sm text-blue-900 dark:text-blue-200">
                    <p class="font-medium">Cada dato va en un solo lugar — no lo repitas en “Proceso de venta”</p>
                    <ul class="list-disc space-y-1 pl-4 text-blue-800 dark:text-blue-300">
                        <li><strong>Personalidad</strong> → quién eres y cómo hablas</li>
                        <li><strong>Reglas</strong> → formato de mensajes (emojis, longitud, etc.)</li>
                        <li><strong>Proceso de venta</strong> → solo casos de venta (stock, catálogo, confirmación)</li>
                        <li><strong>Recordatorios / Protocolo</strong> → sus campos propios abajo</li>
                        <li><strong>Pago</strong> → métodos de pago y tarjeta en la pestaña Pago</li>
                    </ul>
                </div>
            </div>
        </div>

        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <MessageSquare class="h-5 w-5" />
                    Saludo Inicial
                </CardTitle>
                <CardDescription>Primer mensaje al abrir conversación</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="space-y-2">
                    <Label for="saludo">Saludo personalizado</Label>
                    <Textarea
                        id="saludo"
                        v-model="form.saludo_inicial"
                        placeholder="Ej: Hermosa muchas gracias por comunicarte con Roma Store..."
                        :rows="3"
                    />
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <Zap class="h-5 w-5" />
                    Reglas de Comunicación
                </CardTitle>
                <CardDescription>Formato y límites de los mensajes (no repitas la personalidad aquí)</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="space-y-2">
                    <Label for="reglas">Reglas críticas</Label>
                    <Textarea
                        id="reglas"
                        v-model="form.reglas_comunicacion"
                        placeholder="Ej: - Máximo 2 líneas por párrafo&#10;- Máximo 1-2 emojis&#10;- Una sola pregunta por mensaje&#10;- No repetir info ya dada"
                        :rows="8"
                        class="resize-none"
                    />
                </div>
            </CardContent>
        </Card>



        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <AlertCircle class="h-5 w-5" />
                    Recordatorios Automáticos
                </CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="space-y-2">
                    <Label for="recordatorio-3min">Después de 3 minutos sin respuesta</Label>
                    <Textarea
                        id="recordatorio-3min"
                        v-model="form.mensaje_recordatorio_3min"
                        placeholder="Ej: Hermosa, ¿nos confirmas si vas a realizar el pedido por favor?"
                        :rows="2"
                    />
                </div>

                <div class="space-y-2">
                    <Label for="recordatorio-15min">Después de 15 minutos (despedida)</Label>
                    <Textarea
                        id="recordatorio-15min"
                        v-model="form.mensaje_recordatorio_15min"
                        placeholder="Ej: Muchas gracias hermosa, cualquier cosita nos escribes..."
                        :rows="2"
                    />
                </div>

                <div class="space-y-2">
                    <Label for="recordatorio-datos">Si no envía datos completos</Label>
                    <Textarea
                        id="recordatorio-datos"
                        v-model="form.mensaje_recordatorio_datos"
                        placeholder="Ej: Hermosa por favor tus datos para poder programar el envío."
                        :rows="2"
                    />
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Protocolo de traspaso a humano</CardTitle>
                <CardDescription>Reclamos, casos especiales y cuando no puedes responder (Caso D)</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="space-y-2">
                    <Label for="protocolo">Instrucciones de traspaso</Label>
                    <Textarea
                        id="protocolo"
                        v-model="form.protocolo_traspaso"
                        placeholder="Ej: Responde que consultarás con un agente, etiqueta el chat y pausa respuestas automáticas."
                        :rows="4"
                    />
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Confirmación de pagos (24/7)</CardTitle>
                <CardDescription>
                    Mensajes que dirá la IA al recibir comprobantes, fuera de horario, tarjeta y cuando confirmes el pago.
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="space-y-2">
                    <Label for="msg-comprobante">Comprobante recibido (en horario)</Label>
                    <Textarea
                        id="msg-comprobante"
                        v-model="form.mensaje_comprobante_recibido"
                        placeholder="Hermosa, recibimos tu comprobante. En breve lo validamos..."
                        :rows="2"
                    />
                </div>
                <div class="space-y-2">
                    <Label for="msg-comprobante-noche">Comprobante recibido (fuera de horario)</Label>
                    <Textarea
                        id="msg-comprobante-noche"
                        v-model="form.mensaje_comprobante_fuera_horario"
                        placeholder="Recibimos tu comprobante. Tu pedido quedó registrado; mañana lo confirmamos..."
                        :rows="2"
                    />
                </div>

                <PlantillaMensajeEditor
                    id="msg-confirmado"
                    v-model="form.mensaje_pedido_confirmado"
                    label="Pedido confirmado (al verificar pago)"
                    placeholder="Listo {nombre}, tu pedido de {producto} en {color} quedó confirmado. Total S/ {total}..."
                    :show-default-button="true"
                />
                <PlantillaMensajeEditor
                    id="msg-enviado"
                    v-model="form.mensaje_pedido_enviado"
                    label="Pedido enviado"
                    placeholder="Tu pedido de {producto} ya salió hacia {distrito}..."
                />
                <PlantillaMensajeEditor
                    id="msg-entregado"
                    v-model="form.mensaje_pedido_entregado"
                    label="Pedido entregado / cierre"
                    placeholder="Gracias por tu preferencia, {nombre}..."
                />
            </CardContent>
        </Card>


    </div>
</template>
