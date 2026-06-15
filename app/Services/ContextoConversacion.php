<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Message;
use App\Models\Product;
use App\Support\ContextoPedidoActivo;
use App\Support\FormateadorCatalogoProductos;
use App\Support\MensajesEmpresaDefaults;
use App\Support\NormalizadorStockTallas;
use App\Support\PlantillasDatosEmpresa;
use Illuminate\Support\Facades\Cache;

/**
 * Única fuente de verdad del prompt maestro para Gemini (runtime y preview admin).
 */
class ContextoConversacion
{
    public function __construct(
        private ConfiguracionEmpresa $configuracion,
    ) {}

    /**
     * Construye el prompt completo con toda la información de contexto.
     * Unifica: contexto empresa + prompt maestro + catálogo en un único prompt dinámico.
     */
    public function construirPromptCompleto(): string
    {
        $settingsId = CompanySetting::query()->value('id') ?? 0;

        return Cache::remember(
            "contexto_prompt_completo_{$settingsId}",
            300,
            fn (): string => $this->buildPromptCompleto()
        );
    }

    private function buildPromptCompleto(): string
    {
        $empresa = $this->configuracion->obtenerDatosEmpresa();
        $personalidad = $this->configuracion->obtenerPersonalidad();
        $metodos = $this->configuracion->obtenerMetodosPago();
        $contexto = $this->configuracion->obtenerContextoParaPrompt();
        $romaStore = $this->configuracion->obtenerConfiguracionRomaStore();

        // UN ÚNICO PROMPT MAESTRO CON TODO EL CONTEXTO
        $promptCompleto = $this->construirPromptMaestroUnico(
            $empresa,
            $personalidad,
            $metodos,
            $contexto,
            $romaStore
        );

        // Sección final: Catálogo dinámico (productos solamente)
        $formateador = new FormateadorCatalogoProductos($this->simboloMoneda($contexto['moneda'] ?? 'PEN'));
        $productos = Product::where('status', Product::ESTADO_DISPONIBLE)
            ->with('variants')->get();
        $catalogoTexto = $formateador->formatearCatalogo($productos);

        // Armar el prompt final: Prompt maestro + Catálogo dinámico
        $secciones = [
            $promptCompleto,
            $catalogoTexto,
        ];

        return implode("\n\n---\n\n", array_filter($secciones));
    }

    /**
     * Prompt exacto que recibe Gemini (maestro + catálogo + instrucciones del agente).
     * Orden: Sistema → Configuracion → Flujo → Catalogo → Reglas → Checklist
     */
    public function construirPromptParaAgente(): string
    {
        $secciones = $this->construirPromptSecciones();

        return implode("\n\n", array_filter([
            $secciones['sistema'],
            $secciones['configuracion'],
            $secciones['catalogo'],
            $secciones['reglas'],
            $secciones['checklist'],
        ]));
    }

    /**
     * Devuelve las secciones del prompt separadas para preview admin.
     *
     * @return array{sistema: string, configuracion: string, flujo_ventas: string, catalogo: string, reglas: string, checklist: string, completo: string}
     */
    public function construirPromptSecciones(): array
    {
        $settingsId = CompanySetting::query()->value('id') ?? 0;

        return Cache::remember(
            "contexto_prompt_secciones_{$settingsId}",
            300,
            fn (): array => $this->buildPromptSecciones()
        );
    }

    /**
     * @return array{sistema: string, configuracion: string, flujo_ventas: string, catalogo: string, reglas: string, checklist: string, completo: string}
     */
    private function buildPromptSecciones(): array
    {
        $empresa = $this->configuracion->obtenerDatosEmpresa();
        $personalidad = $this->configuracion->obtenerPersonalidad();
        $metodos = $this->configuracion->obtenerMetodosPago();
        $contexto = $this->configuracion->obtenerContextoParaPrompt();
        $romaStore = $this->configuracion->obtenerConfiguracionRomaStore();

        $promptMaestro = $this->construirPromptMaestroUnico($empresa, $personalidad, $metodos, $contexto, $romaStore);

        $reglas = $this->construirInstruccionesAgente();

        $formateador = new FormateadorCatalogoProductos($this->simboloMoneda($contexto['moneda'] ?? 'PEN'));
        $productos = Product::where('status', Product::ESTADO_DISPONIBLE)
            ->with('variants')->get();
        $catalogoTexto = $formateador->formatearCatalogo($productos);

        // Extraer contenido de tags XML
        $sistema = $this->extraerContenidoTag($promptMaestro, 'SISTEMA');
        $configuracion = $this->extraerContenidoTag($promptMaestro, 'CONFIGURACION');
        $reglasContenido = $this->extraerContenidoTag($reglas, 'REGLAS_CRITICAS');
        $checklist = $this->extraerContenidoTag($reglas, 'CHECKLIST_FINAL');

        $completo = implode("\n\n", array_filter([
            $sistema,
            $configuracion,
            $catalogoTexto,
            $reglasContenido,
            $checklist,
        ]));

        return [
            'sistema' => $sistema,
            'configuracion' => $configuracion,
            'flujo_ventas' => '',
            'catalogo' => $catalogoTexto,
            'reglas' => $reglasContenido,
            'checklist' => $checklist,
            'completo' => $completo,
        ];
    }

    private function extraerContenidoTag(string $texto, string $tag): string
    {
        if (preg_match("/<{$tag}>(.*?)<\/{$tag}>/s", $texto, $matches)) {
            return trim($matches[1]);
        }

        return '';
    }

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
            .'- Canal de origen del chat: '.$canalStr."\n"
            ."\n---\n\n"
            .ContextoPedidoActivo::formatear(
                $customer->activeSale,
                $this->configuracion->obtenerMoneda(),
            );
    }

    /**
     * Obtiene el historial de mensajes de una conversación.
     *
     * @return array<int, array{role: string, content: string}>
     */
    public function obtenerHistorial(string $phoneNumber, int $limite = 10, ?int $excluirMessageId = null): array
    {
        $query = Message::query()
            ->where('phone_number', $phoneNumber)
            ->orderBy('created_at', 'desc');

        if ($excluirMessageId !== null) {
            $query->where('id', '!=', $excluirMessageId);
        }

        $mensajes = $query
            ->limit($limite)
            ->get()
            ->reverse();

        return $mensajes->map(fn (Message $msg): array => [
            'role' => $msg->direction === 'outgoing' ? 'assistant' : 'user',
            'content' => $this->formatearContenidoHistorial($msg),
        ])->toArray();
    }

    private function formatearContenidoHistorial(Message $msg): string
    {
        $meta = is_array($msg->metadata) ? $msg->metadata : [];
        $tipo = $meta['type'] ?? 'text';

        if ($tipo === 'audio') {
            $transcript = $meta['transcript'] ?? null;
            if (is_string($transcript) && trim($transcript) !== '') {
                return '(audio) '.trim($transcript);
            }
        }

        if ($tipo === 'image') {
            $caption = $meta['vision']['caption'] ?? null;
            if (is_string($caption) && trim($caption) !== '') {
                return '(imagen) '.trim($caption);
            }
        }

        return $msg->content;
    }

    /**
     * Construye UN ÚNICO prompt maestro dinámico con toda la configuración de la empresa.
     * Integra: Saludos + Reglas + Flujo + Métodos + Entregas + Horarios + Contexto
     */
    private function construirPromptMaestroUnico(
        array $empresa,
        array $personalidad,
        array $metodos,
        array $contexto,
        array $romaStore
    ): string {
        $nombre = $this->valorConfigurado($empresa['nombre'] ?? '', 'Tu Empresa');
        $vendedorNombre = $this->valorConfigurado($empresa['vendedor_nombre'] ?? '', 'nuestra vendedora');
        $vendedorGenero = $this->valorConfigurado($empresa['vendedor_genero'] ?? '', 'Femenino');
        $descripcionEmpresa = trim((string) ($empresa['descripcion_empresa'] ?? ''));
        $actividad = $this->valorConfigurado($contexto['actividad'] ?? '', 'Comercio');
        $celular = trim((string) ($empresa['celular'] ?? ''));
        $email = trim((string) ($empresa['email'] ?? ''));
        $website = trim((string) ($empresa['website'] ?? ''));
        $tono = $personalidad['tono'] ?? 'cálido y cercano';
        $estilo = $personalidad['estilo'] ?? 'natural';
        $descripcionPersonalidad = trim((string) ($personalidad['descripcion'] ?? ''));
        $estiloVentas = trim((string) ($personalidad['estilo_ventas'] ?? ''));
        $respuestaSiEsBot = trim((string) ($personalidad['respuesta_si_es_bot'] ?? ''));
        $moneda = $contexto['moneda'] ?? 'PEN';
        $simboloMonedaCliente = $this->simboloMoneda($moneda);
        $instruccionMoneda = $this->instruccionMonedaCliente($moneda, $simboloMonedaCliente);
        $politicaDevoluciones = trim((string) ($contexto['politica_devoluciones'] ?? ''));
        $restriccionesEspeciales = trim((string) ($contexto['restricciones'] ?? ''));
        $informacionAdicional = trim((string) ($contexto['info_extra'] ?? ''));
        $comisionRaw = $romaStore['pagos']['tarjeta']['comision'] ?? null;
        $comisionTarjeta = $comisionRaw !== null ? (float) $comisionRaw : 5.00;

        // Redes sociales
        $instagram = $empresa['social_networks']['instagram'] ?? '';
        $facebook = $empresa['social_networks']['facebook'] ?? '';
        $tiktok = $empresa['social_networks']['tiktok'] ?? '';

        // Obtener configuración personalizada o usar defaults
        $saludoInicial = $this->valorConfigurado($romaStore['saludo_inicial'] ?? '', $this->obtenerSaludoDefault($nombre));
        $reglasComun = $this->valorConfigurado($romaStore['reglas_comunicacion'] ?? '', $this->obtenerReglasDefault());
        $horarioEntregas = $this->valorConfigurado($romaStore['horario_entregas'] ?? '', 'Horario a confirmar');
        $horarioShalom = $this->valorConfigurado($romaStore['horario_shalom'] ?? '', 'Horario a confirmar');
        $protocolo = $this->valorConfigurado($romaStore['protocolo_traspaso'] ?? '', $this->obtenerProtocoloDefault());
        $recordatorio3min = $this->valorConfigurado($romaStore['recordatorios']['3min'] ?? '', MensajesEmpresaDefaults::recordatorio3Min());
        $recordatorio15min = $this->valorConfigurado($romaStore['recordatorios']['15min'] ?? '', MensajesEmpresaDefaults::recordatorio15Min());
        $recordatorioDatos = trim((string) ($romaStore['recordatorios']['datos'] ?? ''));

        $contactoTexto = $this->construirContactoTexto($celular, $email, $website, $instagram, $facebook, $tiktok);

        // Construir métodos de pago
        $metodosTexto = $this->construirMetodosTexto($metodos, $moneda);

        // Construir tarifario

        // Plantillas de datos (dinámicas)
        $plantillasTexto = $this->construirPlantillasTexto($romaStore['plantillas_datos'] ?? []);

        $informacionAdicionalTexto = $this->construirInformacionAdicionalTexto(
            $politicaDevoluciones,
            $restriccionesEspeciales,
            $informacionAdicional,
            (float) $comisionTarjeta,
        );

        $identidadPersonalidadTexto = $this->construirIdentidadPersonalidadTexto(
            $nombre,
            $actividad,
            $descripcionEmpresa,
            $vendedorNombre,
            $vendedorGenero,
            $descripcionPersonalidad,
            $estiloVentas,
            $respuestaSiEsBot,
            $tono,
            $estilo,
        );

        $recordatoriosTexto = $this->construirRecordatoriosTexto(
            $recordatorio3min,
            $recordatorio15min,
            $recordatorioDatos,
        );

        $sistema = <<<SISTEMA
# INSTRUCCIONES DEL SISTEMA - {$nombre}

## IDENTIDAD Y PERSONALIDAD
{$identidadPersonalidadTexto}

- Actividad: {$actividad}
- {$instruccionMoneda}

## INFORMACIÓN DE CONTACTO
Cómo pueden comunicarse con la empresa:
{$contactoTexto}

## SALUDO INICIAL
Cuando inicie una conversación NUEVA (sin historial previo), responde exactamente así (puedes adaptar emojis si el estilo lo permite):
{$saludoInicial}

**IMPORTANTE:** Si ya hay mensajes en el historial, NO repitas este saludo de bienvenida. Continúa el flujo donde se quedó.

## AUDIOS DE LA CLIENTA
- Los audios se transcriben a texto automáticamente.
- Responde al **contenido** del audio como si fuera un mensaje escrito.
- Nunca respondas solo al hecho de que envió audio o haciendo mención de que envió un audio; responde directamente a lo que dijo.
SISTEMA;

        $configuracion = <<<CONFIGURACION
## REGLAS DE COMUNICACIÓN CRÍTICAS
{$reglasComun}

## MÉTODOS DE PAGO DISPONIBLES
{$metodosTexto}

## POLÍTICAS Y ATENCIÓN
{$informacionAdicionalTexto}

## INFORMACIÓN DE ENTREGAS

### Horarios
- **Entregas por Motorizado:** {$horarioEntregas}
- **Entregas por Shalom:** {$horarioShalom}

## PLANTILLAS DE RECOLECCIÓN DE DATOS

Solo datos de la clienta para coordinar el envío. El vestido y color ya están registrados en el pedido activo — no los pidas de nuevo.

Cuando necesites recopilar datos del cliente, usa estas plantillas:

{$plantillasTexto}

## RECORDATORIOS AUTOMÁTICOS

Si el cliente no responde o no completa datos:
{$recordatoriosTexto}

## PROTOCOLO DE TRASPASO A HUMANO

{$protocolo}
CONFIGURACION;

        return <<<PROMPT
<SISTEMA>
{$sistema}
</SISTEMA>

<CONFIGURACION>
{$configuracion}
</CONFIGURACION>
PROMPT;
    }

    /**
     * Construye la sección de identidad y personalidad del bot.
     */
    private function construirIdentidadPersonalidadTexto(
        string $nombre,
        string $actividad,
        string $descripcionEmpresa,
        string $vendedorNombre,
        string $vendedorGenero,
        string $descripcionPersonalidad,
        string $estiloVentas,
        string $respuestaSiEsBot,
        string $tono,
        string $estilo,
    ): string {
        $texto = "Eres un asistente de ventas de la empresa **{$nombre}**.\n";
        if ($descripcionEmpresa !== '') {
            $texto .= "Información de la Empresa: {$descripcionEmpresa}\n\n";
        }
        $texto .= "Tu nombre es **{$vendedorNombre}** y eres de género **{$vendedorGenero}** (esto define cómo te refieres a ti misma/o en tus respuestas).\n";

        if ($descripcionPersonalidad !== '') {
            $texto .= "Tu personalidad:\n{$descripcionPersonalidad}\n\n";
        } else {
            $texto .= "Tu tono es: {$tono}. Tu estilo de comunicación es: {$estilo}.\n";
        }

        if ($estiloVentas !== '') {
            $texto .= "Tu estilo de ventas:\n{$estiloVentas}\n\n";
        }

        if ($respuestaSiEsBot !== '') {
            $texto .= "\n\nSi te preguntan directamente si eres bot o IA, responde:\n\"{$respuestaSiEsBot}\"";
        }

        return $texto;
    }

    /**
     * Construye los recordatorios automáticos configurados.
     */
    private function construirRecordatoriosTexto(
        string $recordatorio3min,
        string $recordatorio15min,
        string $recordatorioDatos,
    ): string {
        $lineas = [
            "- **Después de 3 minutos sin respuesta:** \"{$recordatorio3min}\"",
            "- **Después de 15 minutos sin respuesta:** \"{$recordatorio15min}\"",
        ];

        if ($recordatorioDatos !== '') {
            $lineas[] = "- **Si no envía datos completos después de 15 minutos:** \"{$recordatorioDatos}\"";
        }

        return implode("\n", $lineas);
    }

    /**
     * Construye el listado de métodos de pago para el prompt.
     */
    private function construirMetodosTexto(array $metodos, string $moneda): string
    {
        if (empty($metodos)) {
            return 'No hay métodos de pago configurados';
        }

        $lineas = [];
        foreach ($metodos as $metodo) {
            $nombreMetodo = $metodo['nombre'] ?? 'Método';
            $destinatario = $metodo['destinatario'] ?? '';
            $numero = $metodo['numero_cuenta'] ?? '';
            $descripcion = $metodo['descripcion'] ?? '';

            $partes = ["- {$nombreMetodo}"];
            if ($destinatario) {
                $partes[] = "Titular: {$destinatario}";
            }
            if ($numero) {
                $partes[] = "Número: {$numero}";
            }
            if ($descripcion && ! str_contains($descripcion, $nombreMetodo)) {
                $partes[] = "Nota: {$descripcion}";
            }

            $lineas[] = implode(' | ', $partes);
        }

        return implode("\n", $lineas);
    }

    private function simboloMoneda(string $moneda): string
    {
        return FormateadorCatalogoProductos::simboloDesdeMoneda($moneda);
    }

    private function instruccionMonedaCliente(string $moneda, string $simbolo): string
    {
        return match ($moneda) {
            'USD' => "Moneda obligatoria en mensajes al cliente: dólares ({$simbolo}). Nunca uses soles ni S/.",
            'EUR' => "Moneda obligatoria en mensajes al cliente: euros ({$simbolo}).",
            default => "Moneda obligatoria en mensajes al cliente: soles peruanos ({$simbolo}). NUNCA uses \$ ni dólares ni USD.",
        };
    }

    /**
     * Construye la sección de políticas y horarios de atención.
     */
    private function construirInformacionAdicionalTexto(
        string $politicaDevoluciones,
        string $restriccionesEspeciales,
        string $informacionAdicional,
        float $comisionTarjeta,
    ): string {
        $lineas = [];

        $lineas[] = "**Horario de atención:**\nAtención 24/7 inmediata de forma continua.";

        if ($politicaDevoluciones !== '') {
            $lineas[] = "**Política de devoluciones:**\n{$politicaDevoluciones}";
        }

        if ($restriccionesEspeciales !== '') {
            $lineas[] = "**Restricciones especiales:**\n{$restriccionesEspeciales}";
        }

        if ($informacionAdicional !== '') {
            $lineas[] = "**Información adicional:**\n{$informacionAdicional}";
        }

        $lineas[] = '**Comisión por pago con tarjeta:** '.$comisionTarjeta.'%';

        return implode("\n\n", $lineas);
    }

    /**
     * Construye las plantillas de recolección de datos dinámicamente.
     */
    private function construirPlantillasTexto(array $plantillas): string
    {
        $plantillas = PlantillasDatosEmpresa::normalizar($plantillas);

        $texto = '';

        if (! empty($plantillas['motorizado'])) {
            $texto .= "**Para Motorizado:**\n";
            foreach ($plantillas['motorizado'] as $etiqueta) {
                $texto .= "- {$etiqueta}\n";
            }
            $texto .= "\n";
        }

        if (! empty($plantillas['shalom'])) {
            $texto .= "**Para Shalom:**\n";
            foreach ($plantillas['shalom'] as $etiqueta) {
                $texto .= "- {$etiqueta}\n";
            }
        }

        return $texto;
    }

    /**
     * Saludo por defecto si no está configurado.
     */
    private function obtenerSaludoDefault(string $nombre): string
    {
        return "Hola, bienvenido a {$nombre}. ¿En qué puedo ayudarte hoy?";
    }

    /**
     * Reglas de comunicación por defecto.
     */
    private function obtenerReglasDefault(): string
    {
        return <<<'REGLAS'
- Sé profesional, amable y empático
- Responde de forma clara y concisa
- Usa un lenguaje natural y cercano
- Máximo 1-2 emojis por mensaje
- Nunca menciones que eres un bot o IA
REGLAS;
    }

    /**
     * Flujo de ventas por defecto.
     */
    private function obtenerFlujoDefault(): string
    {
        return <<<'FLUJO'
1. Saluda al cliente de forma personalizada
2. Entiende qué busca el cliente
3. Recomienda productos del catálogo
4. Menciona precios exactos
5. Cierra con una llamada a la acción clara
6. Si confirma, da instrucciones de pago
FLUJO;
    }

    /**
     * Protocolo de traspaso por defecto.
     */
    private function obtenerProtocoloDefault(): string
    {
        return <<<'PROTOCOLO'
Si no puedes responder una pregunta o surge una situación fuera del flujo normal:
1. Responde: "Voy a realizar la consulta a un agente especializado y en breve le brindamos una respuesta."
2. Etiqueta el chat como "Requiere atención del asesor"
3. Pausa tus respuestas automáticas
4. Espera que el vendedor humano responda
5. Reanuda el flujo desde donde se quedó
PROTOCOLO;
    }

    /**
     * Instrucciones para el agente con herramientas (function calling).
     */
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
- **buscar_productos**: opcional. Usa esto solo si el cliente pide una búsqueda muy compleja que no puedes resolver mirando el CATÁLOGO.
- **verificar_stock**: antes de confirmar talla/color, valida la disponibilidad en vivo. **CRÍTICO:** Compara SIEMPRE la cantidad que pide la clienta con el campo `qty` que devuelve la herramienta. NUNCA confirmes una cantidad mayor al stock real. Si la clienta pide más de lo que hay (ej. pide 10 y el stock `qty` es 7), ofrécele amablemente solo la cantidad que tienes disponible y pregúntale si desea completar el resto con otro color o modelo (ej. "Hermosa, de ese color solo nos quedan 7 unidades disponibles. ¿Deseas que te separemos esas 7 y completamos las demás con otro color?").
- **calcular_envio**: para obtener costo de envío exacto por distrito/método.

### Reglas de Venta Ultra-Críticas

<PROHIBICIONES>
1. NUNCA inventes productos, precios, fotos, stock, descuentos ni costos de envío. Si algo falla, deriva a un humano.
2. NUNCA digas la cantidad exacta de unidades en stock (ej. prohibido decir "tengo 12 azules").
3. NUNCA ofrezcas el precio de TikTok si el canal de origen no es explícitamente "tiktok".
4. NUNCA confirmes pagos tú sola (debes pedir la foto y usar la herramienta registrar_comprobante_recibido).
5. NUNCA repitas preguntas o datos que el cliente ya dio o que ya están confirmados en el Pedido Activo.
</PROHIBICIONES>

<OBLIGATORIOS>
1. SIEMPRE indica solo "tenemos stock", o "quedan pocas unidades" (si verificar_stock indica <= {$umbralEscasez}). Si es 0, di que está agotado.
2. SIEMPRE usa la info de "MÉTODOS DE PAGO" si preguntan cómo pagar.
3. {$instruccionMoneda}
4. Si tu respuesta tiene 2 o más ideas (ej. saludo, detalle, pregunta), DEBES separarlas EXACTAMENTE con la palabra ---SPLIT--- en una línea sola.
</OBLIGATORIOS>

<FORMATO>
- Cero markdown. Emojis con moderación (máx 1 por burbuja).
- Frases cortas y naturales (chat real).
Ejemplo correcto de uso de SPLIT:
Hola hermosa
---SPLIT---
Aquí tienes el detalle de tu pedido...
---SPLIT---
¿Te confirmo esto para el envío?
</FORMATO>
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

    private function valorConfigurado(string $valor, string $default): string
    {
        $valor = trim($valor);

        return $valor !== '' ? $valor : $default;
    }

    private function construirContactoTexto(
        string $celular,
        string $email,
        string $website,
        string $instagram,
        string $facebook,
        string $tiktok,
    ): string {
        $lineas = [];

        if ($celular !== '') {
            $lineas[] = "- **Celular:** {$celular}";
        }

        if ($email !== '') {
            $lineas[] = "- **Email:** {$email}";
        }

        if ($website !== '') {
            $lineas[] = "- **Sitio Web:** {$website}";
        }

        if ($instagram !== '') {
            $lineas[] = "- **Instagram:** {$instagram}";
        }

        if ($facebook !== '') {
            $lineas[] = "- **Facebook:** {$facebook}";
        }

        if ($tiktok !== '') {
            $lineas[] = "- **TikTok:** {$tiktok}";
        }

        return implode("\n", $lineas);
    }
}
