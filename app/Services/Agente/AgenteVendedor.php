<?php

namespace App\Services\Agente;

use App\Exceptions\GeminiQuotaExceededException;
use App\Models\Customer;
use App\Models\LogIA;
use App\Models\Message;
use App\Services\AlertaCuotaGemini;
use App\Services\ClienteGemini;
use App\Services\ConfiguracionAgente;
use App\Services\ContextoConversacion;
use App\Services\ResultadoGeminiAgente;
use Illuminate\Support\Facades\Log;

class AgenteVendedor
{
    public function __construct(
        private ConfiguracionAgente $configuracion,
        private ContextoConversacion $contexto,
        private EjecutorHerramientasAgente $herramientas,
        private AlertaCuotaGemini $alertaCuota,
    ) {}

    public function procesar(Message $mensajeEntrante): ?ResultadoTurnoAgente
    {
        $apiKey = $this->configuracion->obtenerApiKey();
        if (empty($apiKey)) {
            return null;
        }

        $customer = Customer::resolverDesdeMensaje(
            $mensajeEntrante->phone_number,
            $mensajeEntrante->customer_name
        );

        $promptCompleto = $this->contexto->construirPromptParaAgente();

        $historial = $this->contexto->obtenerHistorial($mensajeEntrante->phone_number);
        $contenidoEntrante = trim($mensajeEntrante->content);
        $solicitaFoto = $this->solicitaVerProducto($contenidoEntrante);

        $historial[] = [
            'role' => 'user',
            'content' => $this->enriquecerMensajeEntrante($mensajeEntrante, $solicitaFoto),
        ];

        $cliente = new ClienteGemini(
            apiKey: $apiKey,
            modelo: $this->configuracion->obtenerModelo(),
            temperatura: $this->configuracion->obtenerTemperatura()
        );

        $log = LogIA::registrarRequest(
            $mensajeEntrante->phone_number,
            $this->configuracion->obtenerModelo(),
            $promptCompleto
        );

        $inicio = microtime(true);

        $toolConfig = $solicitaFoto ? [
            'functionCallingConfig' => [
                'mode' => 'ANY',
                'allowedFunctionNames' => ['enviar_foto_producto', 'consultar_pedido_activo'],
            ],
        ] : null;

        $resultado = $cliente->generarConHerramientas(
            promptSistema: $promptCompleto,
            historialMensajes: $historial,
            herramientas: $this->herramientas->definiciones(),
            ejecutor: fn (string $nombre, array $args): array => $this->herramientas->ejecutar(
                $nombre,
                $args,
                $customer->fresh() ?? $customer,
                $mensajeEntrante
            ),
            toolConfig: $toolConfig,
        );

        $tiempoMs = round((microtime(true) - $inicio) * 1000, 2);

        if (! $resultado instanceof ResultadoGeminiAgente) {
            $error = $cliente->obtenerUltimoError();
            if ($error !== null) {
                LogIA::registrarError(
                    $mensajeEntrante->phone_number,
                    $this->configuracion->obtenerModelo(),
                    (int) ($error['http_status'] ?? 0),
                    (string) ($error['codigo'] ?? 'unknown'),
                    (string) ($error['mensaje'] ?? 'Error agente'),
                    $tiempoMs
                );

                if ((int) ($error['http_status'] ?? 0) === 429) {
                    $mensajeCuota = (string) ($error['mensaje'] ?? 'Cuota Gemini agotada');
                    $reintento = ClienteGemini::segundosReintentoDesdeError($mensajeCuota);
                    $this->alertaCuota->marcar($mensajeCuota, $reintento);

                    throw new GeminiQuotaExceededException($mensajeCuota, $reintento);
                }
            }

            return null;
        }

        LogIA::registrarResponse($log->id, $resultado->texto, 0, $tiempoMs);
        $this->alertaCuota->limpiar();

        $customer->refresh();

        return new ResultadoTurnoAgente(
            textoFinal: $resultado->texto,
            iaPausada: (bool) $customer->ia_paused,
            iteraciones: $resultado->iteraciones,
        );
    }

    private function enriquecerMensajeEntrante(Message $mensaje, bool $solicitaFoto): string
    {
        $metadata = is_array($mensaje->metadata) ? $mensaje->metadata : [];
        $tipo = $metadata['type'] ?? 'text';

        if ($tipo === 'image') {
            return '[La clienta envió una imagen/comprobante]. '.$mensaje->content;
        }

        if ($tipo === 'audio') {
            return '[La clienta envió un audio]. '.$mensaje->content;
        }

        if ($tipo === 'location') {
            $latitud = $metadata['latitude'] ?? null;
            $longitud = $metadata['longitude'] ?? null;
            $mapsUrl = $metadata['maps_url'] ?? null;
            $direccion = $metadata['location_address'] ?? $metadata['location_name'] ?? null;

            $detalle = '[La clienta compartió su ubicación actual por WhatsApp';
            if (is_string($direccion) && $direccion !== '') {
                $detalle .= " ({$direccion})";
            }
            if (is_numeric($latitud) && is_numeric($longitud)) {
                $detalle .= " — coordenadas: {$latitud}, {$longitud}";
            }
            if (is_string($mapsUrl) && $mapsUrl !== '') {
                $detalle .= " — enlace: {$mapsUrl}";
            }
            $detalle .= ']. Si el envío es motorizado, guarda la ubicación en actualizar_pedido → customer_data (ubicacion_actual, maps_url, latitude, longitude) y confirma al cliente que la recibiste.';

            return $detalle;
        }

        $contenido = trim($mensaje->content);

        if ($solicitaFoto) {
            $contenido .= "\n\n[Contexto vendedor: la clienta quiere VER el producto o un color. OBLIGATORIO: llama enviar_foto_producto con product_name y color exactos del catálogo (solo colores con foto: sí). Si no sabes el producto/color, llama consultar_pedido_activo primero y luego enviar_foto_producto. La foto se envía sola por WhatsApp; tú solo escribes el caption corto.]";
        }

        return $contenido;
    }

    private function solicitaVerProducto(string $texto): bool
    {
        $t = mb_strtolower(trim($texto));

        if ($t === '') {
            return false;
        }

        $patrones = [
            'foto', 'fotos', 'imagen', 'imágenes', 'imagenes', 'ver el', 'ver la', 'verlo', 'verla',
            'muestr', 'mostr', 'cómo se ve', 'como se ve', 'qué color', 'que color', 'en color',
            'tienen en', 'hay en color', 'modelo', 'mandame', 'mándame', 'envíame', 'enviam',
            'pasame', 'pásame', 'me envías', 'me envias', 'tienes foto', 'tiene foto',
        ];

        foreach ($patrones as $patron) {
            if (str_contains($t, $patron)) {
                return true;
            }
        }

        return false;
    }
}
