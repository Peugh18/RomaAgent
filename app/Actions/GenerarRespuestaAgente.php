<?php

namespace App\Actions;

use App\Actions\Mensajes\EnviarMensajeWhatsappSaliente;
use App\Exceptions\GeminiQuotaExceededException;
use App\Models\Customer;
use App\Models\Message;
use App\Services\Agente\AgenteVendedor;
use App\Services\ConfiguracionAgente;
use App\Services\ConfiguracionEmpresa;
use App\Services\ContextoConversacion;
use App\Support\NormalizadorRespuestaAgente;
use Illuminate\Support\Facades\Log;

class GenerarRespuestaAgente
{
    public function __construct(
        private ConfiguracionAgente $configuracion,
        private ContextoConversacion $contexto,
        private AgenteVendedor $agenteVendedor,
        private EnviarMensajeWhatsappSaliente $enviarMensaje,
        private NormalizadorRespuestaAgente $normalizadorRespuesta,
    ) {}

    /**
     * Genera y envía una respuesta automática del agente IA.
     */
    public function ejecutar(Message $mensajeEntrante): ?Message
    {
        if (! $this->configuracion->estaActivado()) {
            Log::info('IA desactivada, no se genera respuesta automática');

            return null;
        }

        if (empty($this->configuracion->obtenerApiKey())) {
            Log::warning('No hay API key configurada para Gemini');

            return null;
        }

        try {
            Log::info('Agente vendedor procesando mensaje', [
                'phone' => $mensajeEntrante->phone_number,
                'modelo' => $this->configuracion->obtenerModelo(),
            ]);

            $resultado = $this->agenteVendedor->procesar($mensajeEntrante);

            if ($resultado === null || empty($resultado->textoFinal)) {
                Log::warning('Agente no generó respuesta', [
                    'phone' => $mensajeEntrante->phone_number,
                ]);

                return null;
            }

            if ($resultado->iaPausada) {
                Log::info('IA pausada tras herramienta del agente', [
                    'phone' => $mensajeEntrante->phone_number,
                ]);
            }

            $customer = Customer::query()->where('phone_number', $mensajeEntrante->phone_number)->first();
            $customer?->loadMissing('activeSale');
            $moneda = app(ConfiguracionEmpresa::class)->obtenerMoneda();

            $textoFinal = $this->normalizadorRespuesta->procesar(
                $resultado->textoFinal,
                $moneda,
                $customer?->activeSale,
            );

            $partes = $this->normalizadorRespuesta->partirEnMensajes($textoFinal);
            if ($partes === []) {
                return null;
            }

            $ultimoMensaje = null;
            $metadataBase = [
                'generated_by' => 'ai_agent',
                'in_reply_to' => $mensajeEntrante->message_id,
                'model' => $this->configuracion->obtenerModelo(),
                'agent_iterations' => $resultado->iteraciones,
            ];

            foreach ($partes as $indice => $parte) {
                $ultimoMensaje = $this->enviarMensaje->handle(
                    phoneNumber: $mensajeEntrante->phone_number,
                    content: $parte,
                    customerName: $mensajeEntrante->customer_name,
                    metadataExtra: array_merge($metadataBase, [
                        'message_part' => $indice + 1,
                        'message_parts_total' => count($partes),
                    ]),
                    delaySeconds: $indice > 0 ? $indice : 0,
                );
            }

            return $ultimoMensaje;
        } catch (GeminiQuotaExceededException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Error generando respuesta IA', [
                'error' => $e->getMessage(),
                'phone' => $mensajeEntrante->phone_number,
            ]);

            return null;
        }
    }

    /**
     * Determina si se debe generar respuesta automática para este mensaje.
     */
    public function debeResponder(Message $mensaje): bool
    {
        if ($mensaje->direction !== 'incoming') {
            return false;
        }

        if (! $this->configuracion->estaActivado()) {
            return false;
        }

        if (empty($this->configuracion->obtenerApiKey())) {
            return false;
        }

        $customer = Customer::query()->where('phone_number', $mensaje->phone_number)->first();
        if ($customer?->ia_paused) {
            Log::info('IA pausada para este cliente', [
                'phone' => $mensaje->phone_number,
                'reason' => $customer->ia_pause_reason,
            ]);

            return false;
        }

        $metadata = is_array($mensaje->metadata) ? $mensaje->metadata : [];
        $tipo = $metadata['type'] ?? 'text';

        if (! in_array($tipo, ['text', 'image', 'audio', 'location'], true)) {
            Log::info('Tipo de mensaje no soportado para IA', ['type' => $tipo]);

            return false;
        }

        return true;
    }
}
