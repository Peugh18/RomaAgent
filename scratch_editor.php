<?php

$file = 'app/Services/ContextoConversacion.php';
$content = file_get_contents($file);

// 1. Remove catalogo from buildPromptSecciones
$content = str_replace(
    '$catalogo = $this->construirContextoCatalogo();',
    '',
    $content
);

$content = str_replace(
    '$catalogo,',
    '',
    $content
);

$content = str_replace(
    "'catalogo' => \$catalogo,",
    "'catalogo' => '',",
    $content
);

// 2. Add customer channel
$oldCustomer = <<<'PHP'
    public function construirPromptParaAgenteConPedido(?Customer $customer): string
    {
        $prompt = $this->construirPromptParaAgente();

        if ($customer === null) {
            return $prompt;
        }

        $customer->loadMissing('activeSale');

        return $prompt
            ."\n\n---\n\n"
            .ContextoPedidoActivo::formatear(
                $customer->activeSale,
                $this->configuracion->obtenerMoneda(),
            );
    }
PHP;

$newCustomer = <<<'PHP'
    public function construirPromptParaAgenteConPedido(?Customer $customer): string
    {
        $prompt = $this->construirPromptParaAgente();

        if ($customer === null) {
            return $prompt;
        }

        $customer->loadMissing('activeSale');
        
        $canalStr = $customer->channel ?: 'desconocido';

        return $prompt
            ."\n\n---\n\n"
            ."## DATOS DE LA CLIENTA ACTUAL\n"
            ."- Canal de origen del chat: " . $canalStr . "\n"
            ."\n---\n\n"
            .ContextoPedidoActivo::formatear(
                $customer->activeSale,
                $this->configuracion->obtenerMoneda(),
            );
    }
PHP;
$content = str_replace($oldCustomer, $newCustomer, $content);

// 3. Remove Tarifario from construirPromptMaestroUnico
$content = str_replace(
    "\$tarifarioTexto = \$this->construirTarifarioTexto(\$romaStore['entregas'] ?? [], \$moneda);",
    '',
    $content
);

$oldTarifarioBlock = <<<'PHP'
### Horarios
- **Entregas por Motorizado:** {$horarioEntregas}
- **Entregas por Shalom:** {$horarioShalom}

### Tarifario
{$tarifarioTexto}
PHP;

$newTarifarioBlock = <<<'PHP'
### Horarios
- **Entregas por Motorizado:** {$horarioEntregas}
- **Entregas por Shalom:** {$horarioShalom}
PHP;
$content = str_replace($oldTarifarioBlock, $newTarifarioBlock, $content);

// 4. Update construirInstruccionesAgente
$oldInstruccionesBegin = "    public function construirInstruccionesAgente(): string\n    {";

$newInstrucciones = <<<'PHP'
    public function construirInstruccionesAgente(): string
    {
        $settings = CompanySetting::query()->with('mensajes')->first();
        $mensajes = $settings?->mensajes;
        $moneda = $this->configuracion->obtenerMoneda();
        $simbolo = $this->simboloMoneda($moneda);
        $instruccionMoneda = $this->instruccionMonedaCliente($moneda, $simbolo);
        $mensajeComprobante = $this->valorConfigurado(
            $mensajes?->comprobante_recibido ?? '',
            MensajesEmpresaDefaults::comprobanteRecibido(),
        );
        $mensajeNoche = $this->valorConfigurado(
            $mensajes?->comprobante_fuera_horario ?? '',
            MensajesEmpresaDefaults::comprobanteFueraHorario(),
        );
        $mensajeTarjeta = $this->valorConfigurado(
            $mensajes?->espera_link_tarjeta ?? '',
            MensajesEmpresaDefaults::esperaLinkTarjeta(),
        );
        
        // Obtener el umbral de escasez de los settings (por defecto 2 si no existe)
        $romaStore = $this->configuracion->obtenerConfiguracionRomaStore();
        $umbralEscasez = (int) ($romaStore['umbral_escasez'] ?? 2);

        $instruccionTallas = NormalizadorStockTallas::instruccionTallaParaPrompt();

        $reglas = <<<REGLAS
## AGENTE VENDEDOR — HERRAMIENTAS

Eres la vendedora experta: hablas como en el prompt maestro (personalidad, flujo, reglas). Todo lo que escribes al cliente sale de ti; no envíes mensajes tipo sistema ni plantillas vacías.

{$instruccionTallas}

### Herramientas
- **actualizar_pedido**: OBLIGATORIO cada vez que avances la venta (cambios de producto, color, cantidad, envío, total, pago, datos). Actualiza y recalcula antes de hablar.
- **enviar_foto_producto**: llama INMEDIATAMENTE si piden foto o confirmas modelo y la BD indica "foto". NO describas la imagen, da paso con una frase corta.
- **registrar_comprobante_recibido**: cuando envíe captura de pago. Luego di: "{$mensajeComprobante}" (fuera horario: "{$mensajeNoche}").
- **solicitar_atencion_humana**: SOLO para link de tarjeta ({$mensajeTarjeta}), quejas graves o cuando la BD falla/no responde.
- **consultar_pedido_activo**: para recordar el estado de la venta.
- **buscar_productos**: OBLIGATORIO usar cuando pregunten por modelos, colores, precios, disponibilidad o pidan opciones.
- **verificar_stock**: OBLIGATORIO usar ANTES de confirmar producto, color o talla. No asumas disponibilidad por chats pasados.
- **calcular_envio**: para obtener costo de envío exacto por distrito/método.

### Reglas de Venta Ultra-Críticas
1. **Veracidad Absoluta:** NO INVENTES productos, precios, fotos, stock, descuentos ni costos de envío. Si una herramienta falla o BD no responde, no deduzcas: deriva a un humano.
2. **Urgencia Comercial:** SOLO indica que quedan "pocas unidades" cuando verificar_stock indique un stock MENOR O IGUAL a {$umbralEscasez}. Si es 0, di amablemente que está agotado.
3. **Precio TikTok:** El precio de TikTok SOLO debe ofrecerse si el "Canal de origen del chat" (indicado arriba) es "tiktok". NUNCA lo ofrezcas si vienen de otro canal.
4. **Pagos:** Si preguntan cómo pagar, usa la info de "MÉTODOS DE PAGO". Nunca confirmes los pagos tú sola, usa la herramienta.
5. **No Repitas:** No repitas preguntas o datos que el cliente ya dio o que ya están confirmados en el Pedido Activo.
6. {$instruccionMoneda}

### Formato WhatsApp
- Cero markdown. Emojis con moderación (máx 1 por burbuja).
- Frases cortas y naturales (chat real).
- Separa 2 o más ideas usando ---SPLIT--- en línea sola (máx 3 burbujas).
REGLAS;

        $checklist = <<<'CHECKLIST'
<CHECKLIST_FINAL>
Verificación final antes de responder:
1. ¿Usé verificar_stock antes de confirmar algo?
2. ¿Usé ---SPLIT--- si hay ideas distintas?
3. ¿Todo es real (no inventado)?
4. ¿El total coincide con el PEDIDO ACTIVO y usé actualizar_pedido si algo cambió?
</CHECKLIST_FINAL>
CHECKLIST;

        return "<REGLAS_CRITICAS>\n{$reglas}\n</REGLAS_CRITICAS>\n\n{$checklist}";
    }
PHP;

// Find the old function using regex
$pattern = '/    public function construirInstruccionesAgente\(\): string\s*\{.*?(?=    private function valorConfigurado)/s';
$content = preg_replace($pattern, $newInstrucciones."\n\n", $content);

// Remove construirContextoCatalogo()
$patternCat = '/    \/\*\*\s*\*\s*Construye el contexto del catálogo de productos\.\s*\*\/\s*private function construirContextoCatalogo\(\): string\s*\{.*?(?=    \/\*\*\s*\*\s*Instrucciones para el agente con herramientas)/s';
$content = preg_replace($patternCat, '', $content);

// Remove construirTarifarioTexto()
$patternTarif = '/    \/\*\*\s*\*\s*Construye el tarifario de entregas dinámicamente\.\s*\*\/\s*private function construirTarifarioTexto\(array \$entregas, string \$moneda = \'PEN\'\): string\s*\{.*?(?=    \/\*\*\s*\*\s*Construye la sección de políticas y horarios)/s';
$content = preg_replace($patternTarif, '', $content);

file_put_contents($file, $content);
echo "Replaced successfully\n";
