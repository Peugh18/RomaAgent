<?php

namespace App\Services\Agente;

use App\Actions\Mensajes\EnviarMensajeWhatsappSaliente;
use App\Actions\Pedidos\ActualizarPedidoVenta;
use App\Actions\Pedidos\EnviarFotoProductoDesdeAgente;
use App\Actions\Pedidos\RegistrarComprobantePedido;
use App\Models\CompanySetting;
use App\Support\MensajesEmpresaDefaults;
use App\Models\Customer;
use App\Models\Message;
use App\Models\Sale;
use App\Support\HorarioAtencionEmpresa;
use Illuminate\Support\Facades\Log;

class EjecutorHerramientasAgente
{
    public function __construct(
        private ActualizarPedidoVenta $actualizarPedido,
        private EnviarFotoProductoDesdeAgente $enviarFoto,
        private RegistrarComprobantePedido $registrarComprobante,
        private EnviarMensajeWhatsappSaliente $enviarMensaje,
    ) {}

    /**
     * @return list<array{name: string, description: string, parameters: array<string, mixed>}>
     */
    public function definiciones(): array
    {
        return [
            [
                'name' => 'actualizar_pedido',
                'description' => 'Registra o actualiza el pedido de venta mientras conversas. Usa cuando la clienta elige producto, color, envío, pago o datos.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'product_name' => ['type' => 'string', 'description' => 'Nombre del producto en Productos'],
                        'color' => ['type' => 'string'],
                        'size' => ['type' => 'string'],
                        'quantity' => ['type' => 'integer'],
                        'unit_price' => ['type' => 'number'],
                        'delivery_cost' => ['type' => 'number'],
                        'total_amount' => ['type' => 'number'],
                        'payment_method' => ['type' => 'string', 'description' => 'yape, tarjeta, transferencia, etc.'],
                        'delivery_type' => ['type' => 'string', 'description' => 'motorizado o shalom'],
                        'delivery_district' => ['type' => 'string'],
                        'status' => [
                            'type' => 'string',
                            'enum' => ['consultando', 'cotizando', 'datos_listos', 'pago_pendiente', 'pago_recibido'],
                        ],
                        'customer_data' => ['type' => 'object', 'description' => 'nombre, dirección, dni, celular, etc.'],
                        'notes' => ['type' => 'string'],
                    ],
                ],
            ],
            [
                'name' => 'enviar_foto_producto',
                'description' => 'Envía la foto REAL del producto/color por WhatsApp (desde Productos). OBLIGATORIO cuando la clienta pida ver fotos, elijas un modelo/color con foto disponible en catálogo, o el flujo indique mostrar imagen. Llama esta herramienta antes de describir el producto visualmente.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'product_name' => ['type' => 'string'],
                        'color' => ['type' => 'string'],
                        'caption' => ['type' => 'string', 'description' => 'Texto corto que acompaña la foto'],
                    ],
                    'required' => ['product_name', 'color'],
                ],
            ],
            [
                'name' => 'registrar_comprobante_recibido',
                'description' => 'Usa cuando la clienta envía comprobante de pago (Yape, transferencia, voucher). Marca el pedido y pausa respuestas automáticas hasta que el equipo confirme el pago.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'notas' => ['type' => 'string'],
                    ],
                ],
            ],
            [
                'name' => 'solicitar_atencion_humana',
                'description' => 'Pausa la IA y escala a un asesor: tarjeta (link de pago), quejas, casos fuera del flujo, o cuando no puedas resolver.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'motivo' => ['type' => 'string'],
                    ],
                    'required' => ['motivo'],
                ],
            ],
            [
                'name' => 'consultar_pedido_activo',
                'description' => 'Obtiene el estado actual del pedido en curso de esta clienta.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass,
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function ejecutar(string $nombre, array $args, Customer $customer, Message $mensajeEntrante): array
    {
        return match ($nombre) {
            'actualizar_pedido' => $this->ejecutarActualizarPedido($customer, $args),
            'enviar_foto_producto' => $this->ejecutarEnviarFoto($customer, $mensajeEntrante, $args),
            'registrar_comprobante_recibido' => $this->ejecutarComprobante($customer),
            'solicitar_atencion_humana' => $this->ejecutarHumano($customer, $args, $mensajeEntrante),
            'consultar_pedido_activo' => $this->ejecutarConsultarPedido($customer),
            default => ['ok' => false, 'error' => "Herramienta desconocida: {$nombre}"],
        };
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function ejecutarActualizarPedido(Customer $customer, array $args): array
    {
        $sale = $this->actualizarPedido->handle($customer, $args);

        return [
            'ok' => true,
            'sale_id' => $sale->id,
            'status' => $sale->status->value,
            'product_name' => $sale->product_name,
            'color' => $sale->color,
            'total_amount' => (float) $sale->total_amount,
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function ejecutarEnviarFoto(Customer $customer, Message $mensajeEntrante, array $args): array
    {
        $result = $this->enviarFoto->handle(
            (string) ($args['product_name'] ?? ''),
            (string) ($args['color'] ?? ''),
        );

        if (! $result['ok'] || $result['image_url'] === null) {
            Log::warning('EjecutorHerramientasAgente: enviar_foto_producto falló', $result);

            return $result;
        }

        $caption = (string) ($args['caption'] ?? "Aquí está {$result['product_name']} en {$result['color']} 💕");

        Log::info('EjecutorHerramientasAgente: enviando foto WhatsApp', [
            'phone' => $customer->phone_number,
            'product' => $result['product_name'],
            'color' => $result['color'],
            'image_url' => $result['image_url'],
        ]);

        $this->enviarMensaje->handle(
            phoneNumber: $customer->phone_number,
            content: $caption,
            customerName: $mensajeEntrante->customer_name,
            imageUrl: $result['image_url'],
            metadataExtra: ['tool' => 'enviar_foto_producto'],
        );

        return array_merge($result, ['enviado' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function ejecutarComprobante(Customer $customer): array
    {
        $result = $this->registrarComprobante->handle($customer);

        return [
            'ok' => true,
            'mensaje_sugerido' => $result['mensaje'],
            'sale_status' => $result['sale']?->status->value,
            'ia_pausada' => $result['pausado'],
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function ejecutarHumano(Customer $customer, array $args, Message $mensajeEntrante): array
    {
        $motivo = (string) ($args['motivo'] ?? 'Atención humana requerida');
        $customer->pausarIa($motivo);

        $settings = CompanySetting::query()->first();
        $mensajeTarjeta = $settings?->mensaje_espera_link_tarjeta
            ?: MensajesEmpresaDefaults::esperaLinkTarjeta();

        $esTarjeta = str_contains(mb_strtolower($motivo), 'tarjeta');

        return [
            'ok' => true,
            'ia_pausada' => true,
            'motivo' => $motivo,
            'mensaje_sugerido' => $esTarjeta ? $mensajeTarjeta : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function ejecutarConsultarPedido(Customer $customer): array
    {
        /** @var Sale|null $sale */
        $sale = $customer->activeSale;

        if ($sale === null) {
            return ['ok' => true, 'pedido' => null];
        }

        return [
            'ok' => true,
            'pedido' => [
                'id' => $sale->id,
                'product_name' => $sale->product_name,
                'color' => $sale->color,
                'status' => $sale->status->value,
                'total_amount' => (float) $sale->total_amount,
                'payment_method' => $sale->payment_method,
                'delivery_type' => $sale->delivery_type,
                'delivery_district' => $sale->delivery_district,
                'customer_data' => $sale->customer_data ?? [],
                'nota' => 'Producto y color ya confirmados; no volver a pedirlos.',
            ],
        ];
    }
}
