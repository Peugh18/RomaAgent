<?php

namespace App\Services;

use App\Models\Category;
use App\Models\CompanySetting;
use App\Models\Message;
use App\Models\Product;
use App\Support\FormateadorCatalogoProductos;
use App\Support\NormalizadorStockTallas;
use App\Support\MensajesEmpresaDefaults;
use App\Support\PlantillasDatosEmpresa;
use Illuminate\Support\Facades\Cache;

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

        // Sección final: Catálogo dinámico (productos y zonas)
        $catalogo = $this->construirContextoCatalogo();

        // Armar el prompt final: Prompt maestro + Catálogo dinámico
        $secciones = [
            $promptCompleto,
            $catalogo,
        ];

        return implode("\n\n---\n\n", array_filter($secciones));
    }

    /**
     * Prompt exacto que recibe Gemini (maestro + catálogo + instrucciones del agente).
     */
    public function construirPromptParaAgente(): string
    {
        return $this->construirPromptCompleto()
            ."\n\n---\n\n"
            .$this->construirInstruccionesAgente();
    }

    /**
     * Obtiene el historial de mensajes de una conversación.
     *
     * @return array<int, array{role: string, content: string}>
     */
    public function obtenerHistorial(string $phoneNumber, int $limite = 10): array
    {
        $mensajes = Message::where('phone_number', $phoneNumber)
            ->orderBy('created_at', 'desc')
            ->limit($limite)
            ->get()
            ->reverse();

        return $mensajes->map(fn (Message $msg): array => [
            'role' => $msg->direction === 'outgoing' ? 'assistant' : 'user',
            'content' => $msg->content,
        ])->toArray();
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
        $actividad = $this->valorConfigurado($contexto['actividad'] ?? '', 'Comercio');
        $celular = trim((string) ($empresa['celular'] ?? ''));
        $email = trim((string) ($empresa['email'] ?? ''));
        $website = trim((string) ($empresa['website'] ?? ''));
        $tono = $personalidad['tono'] ?? 'cálido y cercano';
        $estilo = $personalidad['estilo'] ?? 'natural';
        $descripcionPersonalidad = trim((string) ($personalidad['descripcion'] ?? ''));
        $respuestaSiEsBot = trim((string) ($personalidad['respuesta_si_es_bot'] ?? ''));
        $moneda = $contexto['moneda'] ?? 'PEN';
        $horarioAtencion = trim((string) ($contexto['horario'] ?? ''));
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
        $flujoVentas = $this->valorConfigurado($romaStore['flujo_ventas'] ?? '', $this->obtenerFlujoDefault());
        $horarioEntregas = $this->valorConfigurado($romaStore['horario_entregas'] ?? '', 'Horario a confirmar');
        $horarioShalom = $this->valorConfigurado($romaStore['horario_shalom'] ?? '', 'Horario a confirmar');
        $protocolo = $this->valorConfigurado($romaStore['protocolo_traspaso'] ?? '', $this->obtenerProtocoloDefault());
        $recordatorio3min = $this->valorConfigurado($romaStore['recordatorios']['3min'] ?? '', MensajesEmpresaDefaults::recordatorio3Min());
        $recordatorio15min = $this->valorConfigurado($romaStore['recordatorios']['15min'] ?? '', MensajesEmpresaDefaults::recordatorio15Min());
        $recordatorioDatos = trim((string) ($romaStore['recordatorios']['datos'] ?? ''));
        $formatoRegistroVenta = trim((string) ($romaStore['formato_registro_venta'] ?? ''));

        $contactoTexto = $this->construirContactoTexto($celular, $email, $website, $instagram, $facebook, $tiktok);

        // Construir métodos de pago
        $metodosTexto = $this->construirMetodosTexto($metodos, $moneda);

        // Construir tarifario
        $tarifarioTexto = $this->construirTarifarioTexto($romaStore['entregas'] ?? [], $moneda);

        // Plantillas de datos (dinámicas)
        $plantillasTexto = $this->construirPlantillasTexto($romaStore['plantillas_datos'] ?? []);

        $informacionAdicionalTexto = $this->construirInformacionAdicionalTexto(
            $horarioAtencion,
            $politicaDevoluciones,
            $restriccionesEspeciales,
            $informacionAdicional,
            (float) $comisionTarjeta,
        );

        $identidadPersonalidadTexto = $this->construirIdentidadPersonalidadTexto(
            $nombre,
            $actividad,
            $descripcionPersonalidad,
            $respuestaSiEsBot,
            $tono,
            $estilo,
        );

        $recordatoriosTexto = $this->construirRecordatoriosTexto(
            $recordatorio3min,
            $recordatorio15min,
            $recordatorioDatos,
        );

        $formatoRegistroTexto = $formatoRegistroVenta !== ''
            ? $formatoRegistroVenta
            : 'Registra internamente la venta con los datos completos del pedido, cliente, envío y monto pagado.';

        return <<<PROMPT
# INSTRUCCIONES DEL SISTEMA - {$nombre}

## IDENTIDAD Y PERSONALIDAD
{$identidadPersonalidadTexto}

- Actividad: {$actividad}
- Moneda: {$moneda}

## INFORMACIÓN DE CONTACTO
Cómo pueden comunicarse con la empresa:
{$contactoTexto}

## SALUDO INICIAL
Cuando inicie una conversación, responde exactamente así (puedes adaptar emojis si el estilo lo permite):
{$saludoInicial}

---

## REGLAS DE COMUNICACIÓN CRÍTICAS
{$reglasComun}

---

## FLUJO DE VENTAS
{$flujoVentas}

---

## MÉTODOS DE PAGO DISPONIBLES
{$metodosTexto}

---

## POLÍTICAS Y ATENCIÓN
{$informacionAdicionalTexto}

---

## INFORMACIÓN DE ENTREGAS

### Horarios
- **Entregas por Motorizado:** {$horarioEntregas}
- **Entregas por Shalom:** {$horarioShalom}

### Tarifario
{$tarifarioTexto}

---

## PLANTILLAS DE RECOLECCIÓN DE DATOS

Solo datos de la clienta para coordinar el envío. El vestido y color ya están registrados en el pedido activo — no los pidas de nuevo.

Cuando necesites recopilar datos del cliente, usa estas plantillas:

{$plantillasTexto}

---

## RECORDATORIOS AUTOMÁTICOS

Si el cliente no responde o no completa datos:
{$recordatoriosTexto}

---

## REGISTRO DE VENTA

Cuando tengas toda la información del pedido, registra la venta internamente con este formato:
{$formatoRegistroTexto}

---

## PROTOCOLO DE TRASPASO A HUMANO

{$protocolo}

PROMPT;
    }

    /**
     * Construye la sección de identidad y personalidad del bot.
     */
    private function construirIdentidadPersonalidadTexto(
        string $nombre,
        string $actividad,
        string $descripcionPersonalidad,
        string $respuestaSiEsBot,
        string $tono,
        string $estilo,
    ): string {
        if ($descripcionPersonalidad !== '') {
            $texto = "Te presentas siempre como **{$nombre}**.\n\n{$descripcionPersonalidad}";
        } else {
            $texto = <<<IDENTIDAD
Te presentas siempre como **{$nombre}**.
Eres un asistente de ventas experto en **{$actividad}**.
- Tono: {$tono}
- Estilo: {$estilo}
- Objetivo: Vender y ayudar al cliente de manera natural y cercana
IDENTIDAD;
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
            return "No hay métodos de pago configurados";
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
        return match ($moneda) {
            'USD' => '$',
            'EUR' => '€',
            default => 'S/',
        };
    }

    /**
     * Construye el tarifario de entregas dinámicamente.
     */
    private function construirTarifarioTexto(array $entregas, string $moneda = 'PEN'): string
    {
        if (empty($entregas)) {
            return 'Tarifario a confirmar';
        }

        $simbolo = $this->simboloMoneda($moneda);
        $texto = '';

        // Motorizado
        if (! empty($entregas['motorizado'])) {
            $texto .= "**Motorizado (por distrito):**\n";
            $distritos = $entregas['motorizado'];
            foreach ($distritos as $distrito => $costo) {
                $texto .= "- {$distrito}: {$simbolo} {$costo}\n";
            }
            $texto .= "\n";
        }

        // Shalom
        if (! empty($entregas['shalom'])) {
            $texto .= "**Shalom (Lima y provincia):**\n";
            $distritos = $entregas['shalom'];
            foreach ($distritos as $distrito => $costo) {
                $texto .= "- {$distrito}: {$simbolo} {$costo}\n";
            }
        }

        return $texto ?: 'Tarifario a confirmar';
    }

    /**
     * Construye la sección de políticas y horarios de atención.
     */
    private function construirInformacionAdicionalTexto(
        string $horarioAtencion,
        string $politicaDevoluciones,
        string $restriccionesEspeciales,
        string $informacionAdicional,
        float $comisionTarjeta,
    ): string {
        $lineas = [];

        if ($horarioAtencion !== '') {
            $lineas[] = "**Horario de atención:**\n{$horarioAtencion}";
        }

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
        return <<<REGLAS
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
        return <<<FLUJO
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
        return <<<PROTOCOLO
Si no puedes responder una pregunta o surge una situación fuera del flujo normal:
1. Responde: "Voy a realizar la consulta a un agente especializado y en breve le brindamos una respuesta."
2. Etiqueta el chat como "Requiere atención del asesor"
3. Pausa tus respuestas automáticas
4. Espera que el vendedor humano responda
5. Reanuda el flujo desde donde se quedó
PROTOCOLO;
    }

    /**
     * Construye el contexto del catálogo de productos.
     */
    private function construirContextoCatalogo(): string
    {
        $moneda = $this->configuracion->obtenerMoneda();
        $formateador = new FormateadorCatalogoProductos(
            FormateadorCatalogoProductos::simboloDesdeMoneda($moneda),
            NormalizadorStockTallas::defaultSizeKey(),
        );

        // Limitar a 100 productos disponibles para mantener el prompt manejable
        // y evitar exceso de tokens en la API de Gemini
        $productos = Product::with(['category', 'variants'])
            ->where('status', Product::ESTADO_DISPONIBLE)
            ->orderBy('name')
            ->limit(100)
            ->get();

        return $formateador->formatearCatalogo($productos);
    }

    /**
     * Instrucciones para el agente con herramientas (function calling).
     */
    public function construirInstruccionesAgente(): string
    {
        $settings = CompanySetting::query()->first();
        $mensajeComprobante = $this->valorConfigurado(
            $settings?->mensaje_comprobante_recibido ?? '',
            MensajesEmpresaDefaults::comprobanteRecibido(),
        );
        $mensajeNoche = $this->valorConfigurado(
            $settings?->mensaje_comprobante_fuera_horario ?? '',
            MensajesEmpresaDefaults::comprobanteFueraHorario(),
        );
        $mensajeTarjeta = $this->valorConfigurado(
            $settings?->mensaje_espera_link_tarjeta ?? '',
            MensajesEmpresaDefaults::esperaLinkTarjeta(),
        );

        return <<<AGENTE
## AGENTE VENDEDOR — HERRAMIENTAS

Eres la vendedora experta: hablas como en el prompt maestro (personalidad, flujo, reglas). Todo lo que escribes al cliente sale de ti; no envíes mensajes tipo sistema ni plantillas vacías.

### Herramientas
- **actualizar_pedido**: cada vez que avances la venta (producto, color, envío, total, pago, datos).
- **enviar_foto_producto**: cuando la clienta pida foto, color, "cómo se ve", o confirmes un modelo — SI el catálogo dice foto: sí. Llama la herramienta INMEDIATAMENTE; envía la imagen real de Productos por WhatsApp (no describas la foto en texto, envíala). Luego responde con caption corto.
- **registrar_comprobante_recibido**: cuando envíe captura de Yape/transferencia/voucher. Después di: "{$mensajeComprobante}" (fuera de horario: "{$mensajeNoche}").
- **solicitar_atencion_humana**: tarjeta ({$mensajeTarjeta}), quejas, casos especiales. Nunca confirmes pagos tú sola.
- **consultar_pedido_activo**: si necesitas recordar en qué paso va la venta.

### Reglas de venta
- Solo productos del catálogo con stock real; no inventes precios, fotos ni productos.
- No confirmes pagos tú sola; usa registrar_comprobante_recibido o solicitar_atencion_humana.
- Al pedir datos de envío: consultar_pedido_activo primero; no repitas producto/color (ver plantillas arriba).
- WhatsApp: sin markdown (**negrita**). Emojis moderados. Una pregunta clara por mensaje.
AGENTE;
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

        $lineas[] = $celular !== ''
            ? "- **Celular:** {$celular}"
            : '- **Celular:** No configurado (completa en Configuración → Empresa)';

        $lineas[] = $email !== ''
            ? "- **Email:** {$email}"
            : '- **Email:** No configurado';

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
