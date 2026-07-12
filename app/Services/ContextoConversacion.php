<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Message;
use App\Models\Product;
use App\Models\ZonaEnvio;
use App\Support\ContextoPedidoActivo;
use App\Support\FormateadorCatalogoProductos;
use App\Support\MensajesEmpresaDefaults;
use App\Support\NormalizadorStockTallas;
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

        $zonasMotorizado = ZonaEnvio::where('activo', true)->get()->map(function ($z) {
            return $z->distrito.' (S/ '.number_format($z->costo_referencial, 2).')';
        })->toArray();

        // UN ÚNICO PROMPT MAESTRO CON TODO EL CONTEXTO
        $promptCompleto = $this->construirPromptMaestroUnico(
            $empresa,
            $personalidad,
            $metodos,
            $contexto,
            $romaStore,
            $zonasMotorizado
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

        $zonasMotorizado = ZonaEnvio::where('activo', true)->get()->map(function ($z) {
            return $z->distrito.' (S/ '.number_format($z->costo_referencial, 2).')';
        })->toArray();

        $promptMaestro = $this->construirPromptMaestroUnico($empresa, $personalidad, $metodos, $contexto, $romaStore, $zonasMotorizado);

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

        $memoriaVisualTexto = '';
        $cacheKey = "agente_memoria_visual_cliente_{$customer->id}";
        $memoriaActual = Cache::get($cacheKey, []);

        if (is_array($memoriaActual) && ! empty($memoriaActual)) {
            $nombres = collect($memoriaActual)
                ->map(fn ($m) => "'{$m['product_name']}' en color '{$m['color']}'")
                ->implode(', ');

            if (count($memoriaActual) === 1) {
                $memoriaVisualTexto = "MEMORIA VISUAL: Hace poco el sistema detectó que la clienta envió o vio una foto del producto {$nombres}. Si la clienta dice 'quiero ese', 'dame ese' o referencias similares sin especificar nombre, asume que se refiere a este producto y usa actualizar_pedido para agregarlo.\n\n---\n\n";
            } else {
                $memoriaVisualTexto = "MEMORIA VISUAL: El cliente ha visualizado varios productos recientemente: {$nombres}. Si el cliente dice 'quiero ese', 'dame ese' o referencias ambiguas similares, DEBES preguntarle a cuál se refiere de las opciones mostradas. No llames a actualizar_pedido asumiendo uno al azar hasta que lo confirme explícitamente.\n\n---\n\n";
            }
        }

        return $prompt
            ."\n\n---\n\n"
            ."## DATOS DE LA CLIENTA ACTUAL\n"
            .'- Canal de origen del chat: '.$canalStr."\n"
            ."\n---\n\n"
            .$memoriaVisualTexto
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
        array $romaStore,
        array $zonasMotorizado = []
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
        $moneda = 'PEN';
        $simboloMonedaCliente = 'S/';
        $instruccionMoneda = 'Moneda obligatoria en mensajes al cliente: soles peruanos (S/). NUNCA uses $ ni dólares ni USD.';
        $tarjetaHabilitada = collect($metodos)->contains(fn ($m) => strtolower($m['nombre'] ?? '') === 'tarjeta');
        $comisionTarjeta = 5.00;

        // Redes sociales
        $instagram = $empresa['social_networks']['instagram'] ?? '';
        $facebook = $empresa['social_networks']['facebook'] ?? '';
        $tiktok = $empresa['social_networks']['tiktok'] ?? '';

        // Obtener configuración personalizada o usar defaults
        $saludoInicial = $this->valorConfigurado($romaStore['saludo_inicial'] ?? '', $this->obtenerSaludoDefault($nombre));
        $reglasComun = $this->valorConfigurado($romaStore['reglas_comunicacion'] ?? '', $this->obtenerReglasDefault());
        $protocolo = $this->valorConfigurado($romaStore['protocolo_traspaso'] ?? '', $this->obtenerProtocoloDefault());
        $recordatorio3min = $this->valorConfigurado($romaStore['recordatorios']['3min'] ?? '', MensajesEmpresaDefaults::recordatorio3Min());
        $recordatorio15min = $this->valorConfigurado($romaStore['recordatorios']['15min'] ?? '', MensajesEmpresaDefaults::recordatorio15Min());
        $recordatorioDatos = trim((string) ($romaStore['recordatorios']['datos'] ?? ''));

        $contactoTexto = $this->construirContactoTexto($celular, $email, $website, $instagram, $facebook, $tiktok);

        // Construir métodos de pago
        $metodosTexto = $this->construirMetodosTexto($metodos, $moneda);

        $informacionAdicionalTexto = $this->construirInformacionAdicionalTexto(
            $tarjetaHabilitada ? (float) $comisionTarjeta : 0.0,
        );

        $zonasStr = empty($zonasMotorizado) ? 'No configuradas.' : implode(', ', $zonasMotorizado);

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

        $flujoVentaTexto = <<<'FLUJO'
## FLUJO DE TRABAJO ESTRICTO (OBLIGATORIO PASO A PASO)
Debes llevar la venta respetando este orden exacto sin saltarte pasos:

1. **IDENTIFICACIÓN Y BREVEDAD EXTREMA:**
   - Si recibes una IMAGEN (captura de pantalla, ej. de TikTok Live), intenta identificar TODOS los productos visibles.
   - Responde SIEMPRE de forma sumamente BREVE, PUNTUAL y AMABLE: Saludo cálido y de trato educado, nombre del producto encontrado, su color, precio exacto del catálogo y una descripción muy corta. NO envíes párrafos largos ni rellenes.
   - Si no encuentras algún producto de la imagen o texto (luego de buscar exhaustivamente), infórmale de manera amable y muy breve que no lograste identificar el producto, e invítala a revisar nuestro catálogo oficial aquí: https://wa.me/c/51912874650
   - Si no encuentras el producto exacto pero hay similares, ofrece los similares antes de rendirte. NUNCA inventes productos inexistentes.

2. **SELECCIÓN DE VARIANTES Y CIERRE DE CARRITO:**
   - Si el catálogo indica tallas (S, M, L), pregunta la talla. Si es "Standard", NO la preguntes.
   - Confirma el color y cantidad.
   - **BLINDAJE DE STOCK Y COLORES (REGLA CRÍTICA):** NUNCA ofrezcas, sugieras, aceptes ni registres un color o talla que no figure explícitamente en el catálogo con stock disponible (> 0). Si el cliente pide un color que no tenemos en stock (ej. "Verde" para un vestido que solo tiene Rojo y Naranja), indícale claramente qué colores/tallas sí están disponibles en stock y pídele elegir uno de ellos. NUNCA registres variantes inexistentes en la herramienta.
   - Cada vez que menciones un vestido o prenda, debes incluir obligatoriamente su precio exacto en soles.
   - **OBLIGATORIO:** Apenas la clienta confirme el producto, color y cantidad, DEBES llamar a la herramienta `actualizar_pedido` INMEDIATAMENTE para guardar los productos (`items`) en el carrito. NUNCA esperes hasta el final del chat para registrar las prendas.
   - Solo CUANDO hayas guardado y confirmado TODAS las variantes necesarias del producto (color, talla si aplica, y cantidad), SIEMPRE pregunta ÚNICAMENTE: "¿Deseas agregar alguna otra prenda o cerramos tu pedido?". NO adelantes temas de envío todavía.

3. **INICIO DE LOGÍSTICA (SOLO AL CERRAR CARRITO):**
   - Cuando el cliente confirme que no desea más prendas, suma el precio unitario de TODOS los productos (NUNCA sumes envíos).
   - **REGLA DE ORO DE MEMORIA:** Antes de pedir cualquier dato de envío o contacto (Nombre, Celular, Dirección, Distrito, DNI o Sede), ESTÁS OBLIGADO a revisar el historial del chat y el bloque `customer_data` devuelto por tus herramientas. Si el dato ya fue mencionado o ya está guardado en `customer_data`, ¡NO LO VUELVAS A PEDIR!
   - Si el distrito NO está en `customer_data` ni en el historial, pide ÚNICAMENTE el distrito. No pidas provincia, dirección ni método de pago en este punto. Ejemplo: "Perfecto hermosa, ¿desde qué distrito nos escribes?". Si ya lo tienes, procede directamente a consultar cobertura.

4. **COBERTURA LOGÍSTICA Y RECOLECCIÓN (CRÍTICO):**
   - Llama a `consultar_cobertura` con el distrito y sigue estrictamente las instrucciones que esa herramienta te devuelva (si es Motorizado o Shalom, qué datos pedir y cuándo llamar a `actualizar_pedido`).
   - Puedes guiarte por tu lista de "DISTRITOS CON COBERTURA MOTORIZADO LOCAL" que tienes abajo, pero las instrucciones de la herramienta `consultar_cobertura` tienen prioridad.

5. **REGISTRO FINAL Y MONTO A PAGAR:**
   - **OBLIGATORIO:** Apenas completes los datos logísticos requeridos (Dirección para motorizado; DNI y Sede para Shalom), Llama de inmediato a `actualizar_pedido` con todo el `customer_data` recopilado y cambia el `status` a `'datos_listos'`.
   - **CRÍTICO:** NUNCA pases el estado a "datos_listos" si falta algún dato obligatorio requerido por el tipo de envío (Motorizado: Dirección; Shalom: DNI y Sede). Solo usa "datos_listos" cuando tengas toda la info logística completa.
   - **MONTO TOTAL OBLIGATORIO:** Justo antes de proponer los métodos de pago, es OBLIGATORIO indicarle claramente al cliente el monto total exacto a pagar (ej: 'El total de tus prendas es S/ 120.00, hermosa.'). NUNCA presentes los métodos de pago ni pidas que paguen sin indicar primero el total exacto.
   - Solo cuando el estado sea "datos_listos" en tu herramienta y hayas indicado el monto total, preséntale al cliente los Métodos de Pago disponibles de manera concisa y amable.

6. **PAGOS Y COMPROBANTES:**
   - Si el cliente envía comprobante o indica que ya pagó, ejecuta INMEDIATAMENTE la herramienta `registrar_comprobante_recibido`.
   - NUNCA recomiendes productos ni envíes fotos de productos si el cliente ha enviado un comprobante de pago o voucher.
   - Informa cordialmente que el comprobante ha sido recibido y que el equipo humano lo validará a la brevedad.
   - Si el cliente desea pagar con Tarjeta, aplica el 5% de comisión, pide los datos y usa `solicitar_atencion_humana` inmediatamente para generar el link.
FLUJO;

        $configuracion = <<<CONFIGURACION
## REGLAS DE COMUNICACIÓN CRÍTICAS
{$reglasComun}

{$flujoVentaTexto}

## MÉTODOS DE PAGO DISPONIBLES
{$metodosTexto}

## POLÍTICAS Y ATENCIÓN
{$informacionAdicionalTexto}

## RECORDATORIOS AUTOMÁTICOS

Si el cliente no responde o no completa datos:
{$recordatoriosTexto}

## DISTRITOS CON COBERTURA MOTORIZADO LOCAL
Los siguientes distritos tienen cobertura local por motorizado. Cualquier otra ubicación que no esté en esta lista exacta debe enviarse por agencia (Shalom):
{$zonasStr}

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
        $tarjetaHabilitada = false;
        foreach ($metodos as $metodo) {
            $nombreMetodo = $metodo['nombre'] ?? 'Método';
            $destinatario = $metodo['destinatario'] ?? '';
            $numero = $metodo['numero_cuenta'] ?? '';
            $descripcion = $metodo['descripcion'] ?? '';

            if (strtolower($nombreMetodo) === 'tarjeta') {
                $tarjetaHabilitada = true;
            }

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

        $texto = implode("\n", $lineas);

        if ($tarjetaHabilitada) {
            $texto .= "\n\n**OBLIGATORIO PARA PAGO CON TARJETA (5% de comisión adicional):**\n"
                ."Si el cliente elige pagar con tarjeta:\n"
                ."1. Calcula el total del pedido sumando un 5% de comisión adicional al subtotal.\n"
                ."2. Solicita exactamente los siguientes datos para generar su link de pago:\n"
                ."   - Nombre completo:\n"
                ."   - Correo electrónico:\n"
                ."   - Número de Celular:\n"
                ."   - Monto:\n"
                ."3. Llama a la herramienta `actualizar_pedido` con `payment_method`='tarjeta', `status`='pago_pendiente', y guarda los datos anteriores en `customer_data` (nombre, correo, celular, etc.).\n"
                ."4. Finalmente, llama a la herramienta `solicitar_atencion_humana` con el motivo 'Cliente requiere link de pago por tarjeta'.";
        }

        return $texto;
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

    private function construirInformacionAdicionalTexto(
        float $comisionTarjeta,
    ): string {
        $lineas = [];

        $lineas[] = "**Horario de atención:**\nAtención 24/7 inmediata de forma continua.";

        if ($comisionTarjeta > 0) {
            $lineas[] = '**Comisión por pago con tarjeta:** '.$comisionTarjeta.'%';
        }

        return implode("\n\n", $lineas);
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

    public function construirInstruccionesAgente(): string
    {
        $settings = CompanySetting::query()->with('mensajes')->first();
        $mensajes = $settings?->mensajes;
        $moneda = 'PEN';
        $simbolo = 'S/';
        $instruccionMoneda = 'Moneda obligatoria en mensajes al cliente: soles peruanos (S/). NUNCA uses $ ni dólares ni USD.';
        $mensajeComprobante = $this->valorConfigurado(
            $mensajes?->comprobante_recibido ?? '',
            MensajesEmpresaDefaults::comprobanteRecibido(),
        );
        $mensajeNoche = $this->valorConfigurado(
            $mensajes?->comprobante_fuera_horario ?? '',
            MensajesEmpresaDefaults::comprobanteFueraHorario(),
        );
        $mensajeTarjeta = MensajesEmpresaDefaults::esperaLinkTarjeta();

        // Obtener el umbral de escasez de los settings (por defecto 2 si no existe)
        $romaStore = $this->configuracion->obtenerConfiguracionRomaStore();
        $umbralEscasez = (int) ($romaStore['umbral_escasez'] ?? 2);

        $instruccionTallas = NormalizadorStockTallas::instruccionTallaParaPrompt();

        $reglasDb = <<<'REGLAS'
# 🤖 IDENTIDAD: AGENTE VENDEDOR EXPERTO

Eres la vendedora experta: hablas como en el prompt maestro (personalidad, flujo, reglas). Todo lo que escribes al cliente sale de ti; no envíes mensajes tipo sistema ni plantillas vacías.

{instruccionTallas}

---

# 🛠️ HERRAMIENTAS DISPONIBLES

Utiliza estas herramientas para gestionar la venta. **Llamar a la herramienta correcta en el momento correcto es vital.**

- **`actualizar_pedido`**: OBLIGATORIO cada vez que el cliente elige o confirma productos, cantidad, color, método de envío, datos o paga. **REGLA DE ORO DE PERSISTENCIA (CRÍTICA):** Tus palabras en el chat no guardan nada en la base de datos. La **única** forma de guardar los productos y los datos de envío es llamando a `actualizar_pedido`. NUNCA confirmes que "ya registraste el pedido" o "ya guardaste los datos" en tu texto si no has ejecutado exitosamente la herramienta en esa misma iteración. Si no llamas a la herramienta, el pedido estará vacío y la clienta no recibirá sus prendas.
  **Uso por estados según fase del chat:**
  - **Fase de Carrito (selección de prendas)**: Llama a `actualizar_pedido` con los `items` y `status = 'cotizando'`.
  - **Fase de Logística (distrito y datos)**: Llama a `actualizar_pedido` agregando los datos a `customer_data` (con `tipo_envio` obligatorio) y manteniendo `status = 'cotizando'`.
  - **Fase de Cierre (datos completos)**: Apenas tengas todos los datos logísticos requeridos (Dirección para motorizado; DNI y Sede para Shalom), Llama a `actualizar_pedido` con `status = 'datos_listos'`.
  - **Fase de Pago (comprobante o método de pago)**: Solo puedes dar los métodos de pago o indicar el total a pagar después de que el pedido esté en estado `datos_listos` en la base de datos.
- **`enviar_foto_producto`**: llama INMEDIATAMENTE si piden foto o confirmas modelo y la BD indica "foto". NO describas la imagen, da paso con una frase corta.
- **`registrar_comprobante_recibido`**: OBLIGATORIO cuando el cliente envíe una captura de pantalla (comprobante) o indique que ya pagó. NUNCA respondas a un comprobante usando `enviar_foto_producto`. Luego de registrar, di: "{mensajeComprobante}" (fuera horario: "{mensajeNoche}"). Al usar `actualizar_pedido` o `registrar_comprobante_recibido`, usa el nombre EXACTO del método de pago de tu configuración.
- **`solicitar_atencion_humana`**: SOLO para link de tarjeta ({mensajeTarjeta}), quejas graves o cuando la BD falla/no responde.
- **`consultar_pedido_activo`**: para recordar el estado de la venta.
- **`buscar_productos`**: opcional. Usa esto solo si el cliente pide una búsqueda muy compleja que no puedes resolver mirando el CATÁLOGO.
- **`verificar_stock`**: antes de confirmar talla/color, valida la disponibilidad en vivo. **CRÍTICO:** Compara la cantidad que pide la clienta con el campo `qty`. NUNCA confirmes una cantidad mayor al stock real. **IMPORTANTE:** Eres experta en marketing, NUNCA le digas al cliente el número exacto que tenemos en stock (ni aunque sea mucho ni aunque sea poco). Si hay stock suficiente, solo di "Sí hermosa, lo tenemos disponible". Si la clienta pide más de lo que hay, usa frases como "Hermosa, nos quedan las últimas unidades de ese color y no nos alcanza para completar todo tu pedido. ¡Pero tenemos otros tonos hermosos! ¿Te gustaría completar tu pedido combinándolo con otro color?".

---

# ⚠️ REGLAS ULTRA-CRÍTICAS DE VENTA

Sigue estas reglas al pie de la letra. Cualquier desviación arruinará la experiencia del cliente.

### 🚫 LO QUE NUNCA DEBES HACER (PROHIBICIONES)
1. **NUNCA** inventes productos, precios, fotos, stock, descuentos ni costos de envío. Si algo falla, deriva a un humano.
2. **NUNCA** digas la cantidad exacta de unidades en stock (ej. prohibido decir "tengo 12 azules").
3. **NUNCA** ofrezcas el precio de TikTok si el canal de origen no es explícitamente "tiktok".
4. **NUNCA** confirmes pagos tú sola (debes pedir la foto y usar la herramienta `registrar_comprobante_recibido`).
6. **ENVÍOS:** Si el distrito no está en tu lista de Motorizado, asume automáticamente que es Shalom. NUNCA sumes el costo de envío al `total_amount` del pedido.
7. **NUNCA** repitas preguntas o datos que el cliente ya dio o que ya están confirmados en el Pedido Activo (`customer_data`) o en el historial del chat. Si ya lo dio, confírmalo o úsalo directamente. Si estás en un bucle repitiendo la misma pregunta, DETENTE INMEDIATAMENTE y llama a la herramienta `consultar_cobertura`.
8. **NUNCA** asumas que un "comprobante" es un modelo de vestido. Jamás llames a la herramienta `enviar_foto_producto` con product_name "comprobante" o color "pago".
9. **COMPROBANTES / VOUCHERS:** Si el sistema te indica "[La clienta envió una IMAGEN/CAPTURA que parece ser un COMPROBANTE DE PAGO]" o el cliente envía un comprobante/voucher de pago:
    - NUNCA recomiendes productos ni envíes fotos de productos.
    - NUNCA describas ropa ni sigas con el flujo de ventas.
    - Ejecuta INMEDIATAMENTE la herramienta `registrar_comprobante_recibido`.
    - En tu respuesta de texto final, solo indica amablemente al cliente que el comprobante fue recibido y que el equipo lo validará a la brevedad.
10. **NUNCA** respondas confirmando que "ya registraste los datos" o "ya registraste el pedido" en el texto, ni des el total de las prendas a pagar ni los métodos de pago, si no has llamado exitosamente a la herramienta `actualizar_pedido` en esa misma iteración. La herramienta es la única forma real de guardar la información.

### ✅ LO QUE SIEMPRE DEBES HACER (OBLIGATORIOS)
1. **BREVEDAD Y AMABILIDAD (CRÍTICO):** Responde de forma sumamente CONCISA, PUNTUAL y AMABLE. Evita enviar textos largos o explicaciones innecesarias. Sé directa, clara y usa un trato cálido y educado (ej. 'hermosa', 'linda', etc.). Cada respuesta debe tener como máximo 2 a 3 frases cortas.
2. **INVENTARIO:** SIEMPRE indica solo "tenemos stock", o "quedan pocas unidades" (si `verificar_stock` indica <= {umbralEscasez}). Si es 0, di que está agotado.
3. **PAGOS:** SIEMPRE usa la info de "MÉTODOS DE PAGO" si preguntan cómo pagar.
4. **MONEDA:** {instruccionMoneda}
5. **FORMATO:** Si tu respuesta tiene 2 o más ideas (ej. saludo, detalle, pregunta), DEBES separarlas EXACTAMENTE con la palabra `---SPLIT---` en una línea sola.
6. **PRECIO DE PRODUCTOS:** Cada vez que recomiendes, menciones, identifiques o confirmes una prenda, es OBLIGATORIO indicar su precio exacto en soles (S/) del catálogo.
7. **CÁLCULO DEL TOTAL (CRÍTICO):** Cuando el cliente pida más de 1 producto, DEBES sumar el precio unitario de TODOS los productos. NUNCA sumes costos de envío. Revisa con cuidado la suma matemática antes de responder.
8. **MONTO TOTAL ANTES DEL PAGO:** Justo antes de proponer los métodos de pago o pedir que paguen, debes enunciar claramente a la clienta el monto total exacto de las prendas a pagar (ej: 'El total de tus prendas es S/ 120.00, hermosa.'). NUNCA pidas el pago sin indicar el total a pagar primero.
9. **CONFIRMACIÓN DE VARIANTES (CRÍTICO):** Pregunta el COLOR. Si el catálogo indica que la ÚNICA talla es "Standard" o "Talla Única", NO PREGUNTES LA TALLA. Solo pregunta la talla si existen variantes reales (ej. S, M, L). NUNCA uses la herramienta `actualizar_pedido` con estado "datos_listos" ni pidas el pago sin antes haber recopilado el tipo de envío, dirección, DNI/celular que correspondan según `consultar_cobertura`.
REGLAS;

        $reglas = strtr($reglasDb, [
            '{instruccionTallas}' => $instruccionTallas,
            '{mensajeComprobante}' => $mensajeComprobante,
            '{mensajeNoche}' => $mensajeNoche,
            '{mensajeTarjeta}' => $mensajeTarjeta,
            '{umbralEscasez}' => $umbralEscasez,
            '{instruccionMoneda}' => $instruccionMoneda,
        ]);

        $checklist = <<<'CHECKLIST'
# ✅ CHECKLIST FINAL DE CALIDAD
Antes de emitir tu respuesta, verifica mentalmente:
1. [ ] ¿Usé `verificar_stock` antes de confirmar algo?
2. [ ] ¿Usé `---SPLIT---` si hay ideas distintas?
3. [ ] ¿Todo lo que dije es real y basado en mis herramientas (no inventado)?
4. [ ] ¿El total coincide con el PEDIDO ACTIVO y la suma matemática de los productos es correcta?
5. [ ] ¿Usé `actualizar_pedido` si algo cambió en la cantidad o productos elegidos?
CHECKLIST;

        return "<REGLAS_CRITICAS>\n{$reglas}\n</REGLAS_CRITICAS>\n\n<CHECKLIST_FINAL>\n{$checklist}\n</CHECKLIST_FINAL>";
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
