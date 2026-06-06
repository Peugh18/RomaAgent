<script setup lang="ts">
import { computed, inject } from 'vue';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { MessageSquare, Zap, Users, AlertCircle, Info } from 'lucide-vue-next';
import type { CompanySettingsForm } from '@/types/settings';
import { PLANTILLAS_DATOS_DEFECTO } from '@/lib/plantillasDatos';

const plantillaMotorizadoDefault = Object.values(PLANTILLAS_DATOS_DEFECTO.motorizado).join('\n');
const plantillaShalomDefault = Object.values(PLANTILLAS_DATOS_DEFECTO.shalom).join('\n');

const form = inject('companyConfigForm') as CompanySettingsForm & Record<string, unknown>;

if (!form) {
    throw new Error('companyConfigForm no está disponible');
}

const plantillaMotorizadoTexto = computed({
    get: (): string => {
        const motorizado = form.plantillas_datos?.motorizado;
        if (motorizado && Object.keys(motorizado).length > 0) {
            return Object.values(motorizado).join('\n');
        }

        return plantillaMotorizadoDefault;
    },
    set: (value: string) => {
        const campos = value.split('\n').filter((linea) => linea.trim());
        const motorizado: Record<string, string> = {};
        campos.forEach((campo, idx) => {
            motorizado[`campo_${idx}`] = campo;
        });

        if (!form.plantillas_datos) {
            form.plantillas_datos = { motorizado: {}, shalom: {} };
        }

        form.plantillas_datos.motorizado = motorizado;
    },
});

const plantillaShalomTexto = computed({
    get: (): string => {
        const shalom = form.plantillas_datos?.shalom;
        if (shalom && Object.keys(shalom).length > 0) {
            return Object.values(shalom).join('\n');
        }

        return plantillaShalomDefault;
    },
    set: (value: string) => {
        const campos = value.split('\n').filter((linea) => linea.trim());
        const shalom: Record<string, string> = {};
        campos.forEach((campo, idx) => {
            shalom[`campo_${idx}`] = campo;
        });

        if (!form.plantillas_datos) {
            form.plantillas_datos = { motorizado: {}, shalom: {} };
        }

        form.plantillas_datos.shalom = shalom;
    },
});
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
                        <li><strong>Plantillas</strong> → datos a pedir por motorizado/Shalom</li>
                        <li><strong>Proceso de venta</strong> → solo casos de venta (stock, catálogo, confirmación)</li>
                        <li><strong>Recordatorios / Protocolo / Registro</strong> → sus campos propios abajo</li>
                        <li><strong>Entregas</strong> → horarios | <strong>Zonas de Entrega</strong> → tarifas | <strong>Pago</strong> → Yape/tarjeta</li>
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
                <CardTitle>Plantillas de recolección de datos</CardTitle>
                <CardDescription>Lista de campos a pedir según tipo de envío</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <Tabs default-value="motorizado" class="w-full">
                    <TabsList class="grid w-full grid-cols-2">
                        <TabsTrigger value="motorizado">Motorizado</TabsTrigger>
                        <TabsTrigger value="shalom">Shalom</TabsTrigger>
                    </TabsList>

                    <TabsContent value="motorizado" class="space-y-4">
                        <div class="space-y-2">
                            <Label>Campos motorizado (uno por línea)</Label>
                            <Textarea v-model="plantillaMotorizadoTexto" :rows="5" />
                        </div>
                    </TabsContent>

                    <TabsContent value="shalom" class="space-y-4">
                        <div class="space-y-2">
                            <Label>Campos Shalom (uno por línea)</Label>
                            <Textarea v-model="plantillaShalomTexto" :rows="5" />
                        </div>
                    </TabsContent>
                </Tabs>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <Users class="h-5 w-5" />
                    Proceso de Venta
                </CardTitle>
                <CardDescription>
                    Solo lógica de venta: identificar producto, stock, catálogo y confirmación. No incluyas horarios, tarifas, pagos, recordatorios ni plantillas de datos.
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="space-y-2">
                    <Label for="flujo">Casos y pasos de venta</Label>
                    <Textarea
                        id="flujo"
                        v-model="form.flujo_ventas"
                        placeholder="Ej: Caso A - clienta envía foto → identificar modelo y stock...&#10;Caso B - consulta por nombre/color...&#10;Caso C - pide catálogo → preguntar qué busca...&#10;Confirmación → preguntar Shalom o motorizado"
                        :rows="12"
                    />
                    <p class="text-xs text-muted-foreground">
                        Para reclamos o traspaso a humano usa el campo <strong>Protocolo</strong>, no lo dupliques aquí.
                    </p>
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
                <div class="space-y-2">
                    <Label for="msg-tarjeta">Cliente elige tarjeta</Label>
                    <Textarea
                        id="msg-tarjeta"
                        v-model="form.mensaje_espera_link_tarjeta"
                        placeholder="Te comunico con el equipo para el link de pago..."
                        :rows="2"
                    />
                </div>
                <div class="space-y-2">
                    <Label for="msg-confirmado">Pedido confirmado (al pulsar Confirmar pago)</Label>
                    <Textarea
                        id="msg-confirmado"
                        v-model="form.mensaje_pedido_confirmado"
                        placeholder="Listo hermosa, tu pedido de {producto} quedó confirmado. Total S/ {total}..."
                        :rows="2"
                    />
                    <p class="text-xs text-muted-foreground">Variables: {'{producto}'}, {'{color}'}, {'{total}'}</p>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Formato de registro de venta</CardTitle>
                <CardDescription>Plantilla interna cuando el pedido esté completo</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="space-y-2">
                    <Label for="formato-registro">Datos a registrar</Label>
                    <Textarea
                        id="formato-registro"
                        v-model="form.formato_registro_venta"
                        placeholder="Ej: Celular, nombre, producto, color, cantidad, envío, monto..."
                        :rows="8"
                    />
                </div>
            </CardContent>
        </Card>
    </div>
</template>
