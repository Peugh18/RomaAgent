<?php

namespace App\Services\Agente;

use App\Actions\Mensajes\EnviarMensajeWhatsappSaliente;
use App\Actions\Pedidos\ActualizarPedidoVenta;
use App\Actions\Pedidos\EnviarFotoProductoDesdeAgente;
use App\Actions\Pedidos\RegistrarComprobantePedido;
use App\Enums\SaleStatus;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Message;
use App\Models\Sale;
use App\Models\VentaConfig;
use App\Services\Agente\Tools\BuscarProductosTool;
use App\Services\Agente\Tools\ConsultarCoberturaTool;
use App\Services\Agente\Tools\VerificarStockTool;
use App\Support\FormateadorCatalogoProductos;
use App\Support\MensajesEmpresaDefaults;
use App\Support\NormalizadorStockTallas;
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
                        'items' => [
                            'type' => 'array',
                            'description' => 'Lista de productos. OPCIONAL. Si el cliente solo está dando sus datos de envío/pago y NO está agregando ni cambiando productos en este momento, NO envíes este parámetro o envíalo vacío para conservar los productos que ya están guardados.',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'product_name' => ['type' => 'string', 'description' => 'Nombre del producto'],
                                    'color' => ['type' => 'string'],
                                    'size' => ['type' => 'string'],
                                    'quantity' => ['type' => 'integer'],
                                    'unit_price' => ['type' => 'number'],
                                ],
                                'required' => ['product_name', 'quantity'],
                            ],
                        ],
                        'total_amount' => ['type' => 'number'],
                        'payment_method' => ['type' => 'string', 'description' => 'Método de pago utilizado (ej. yape, plin, depósito, etc., DEBE coincidir con el método configurado en la tienda)'],
                        'status' => [
                            'type' => 'string',
                            'enum' => ['consultando', 'cotizando', 'datos_listos', 'pago_pendiente', 'pago_recibido'],
                        ],
                        'customer_data' => [
                            'type' => 'object',
                            'description' => 'Guarda todos los datos logísticos y de contacto aquí. OBLIGATORIO incluir SIEMPRE la clave "tipo_envio" (valores válidos: "motorizado" o "shalom") si guardas algún dato de envío. Para motorizado: nombre_completo, celular, direccion, distrito, costo_referencial. Para shalom: nombre_completo, celular, distrito, provincia, dni, sede_shalom.',
                        ],
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
                'description' => 'Usa cuando la clienta envía comprobante de pago (captura, voucher o foto de pago). Marca el pedido y pausa respuestas automáticas hasta que el equipo confirme el pago. Si la clienta confirma los datos del pedido junto con el voucher, incluye los items.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'payment_method' => ['type' => 'string', 'description' => 'Método de pago deducido del comprobante (Yape, Plin, BCP, BBVA, etc.)'],
                        'notas' => ['type' => 'string'],
                    ],
                ],
            ],
            [
                'name' => 'solicitar_atencion_humana',
                'description' => 'Pausa la IA y escala a un asesor: SOLO quejas graves o casos imposibles con el catálogo. NO uses para comprobantes de pago ni preguntas que están en el prompt.',
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
            ConsultarCoberturaTool::definition(),
            BuscarProductosTool::definition(),
            VerificarStockTool::definition(),
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function ejecutar(string $nombre, array $args, Customer $customer, Message $mensajeEntrante): array
    {
        $meta = is_array($mensajeEntrante->metadata) ? $mensajeEntrante->metadata : [];
        $esComprobante = (($meta['vision']['inbound_profile']['tipo_mensaje'] ?? '') === 'comprobante');

        if ($esComprobante && in_array($nombre, ['enviar_foto_producto', 'buscar_productos'], true)) {
            return [
                'ok' => false,
                'error' => 'Se ha detectado un comprobante de pago en el mensaje entrante. Está estrictamente PROHIBIDO recomendar productos o enviar fotos en este momento. Debes registrar el comprobante inmediatamente usando "registrar_comprobante_recibido" (si tienes los datos de envío completos) o pedir los datos de envío faltantes al cliente.',
            ];
        }

        return match ($nombre) {
            'actualizar_pedido' => $this->ejecutarActualizarPedido($customer, $args),
            'enviar_foto_producto' => $this->ejecutarEnviarFoto($customer, $mensajeEntrante, $args),
            'registrar_comprobante_recibido' => $this->ejecutarComprobante($customer, $mensajeEntrante, $args),
            'solicitar_atencion_humana' => $this->ejecutarHumano($customer, $args, $mensajeEntrante),
            'consultar_pedido_activo' => $this->ejecutarConsultarPedido($customer),
            'buscar_productos' => BuscarProductosTool::execute($args),
            'verificar_stock' => VerificarStockTool::execute($args),
            'consultar_cobertura' => ConsultarCoberturaTool::execute($args, $customer),
            default => ['ok' => false, 'error' => "Herramienta desconocida: {$nombre}"],
        };
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function ejecutarActualizarPedido(Customer $customer, array $args): array
    {
        try {
            $sale = $this->actualizarPedido->handle($customer, $args);

            return $this->formatearRespuestaPedido($sale);
        } catch (\InvalidArgumentException $e) {
            return [
                'ok' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function formatearRespuestaPedido(Sale $sale): array
    {
        $moneda = VentaConfig::query()->value('moneda')
            ?? CompanySetting::query()->value('moneda')
            ?? 'PEN';
        $simbolo = FormateadorCatalogoProductos::simboloDesdeMoneda((string) $moneda);
        $total = (float) $sale->total_amount;
        $items = $sale->items;

        $desgloseItems = $items->map(function ($item) {
            $qty = max(1, (int) $item->quantity);

            return sprintf('%d × %s %s (%.2f)', $qty, $item->product_name, $item->color ?? '', (float) $item->unit_price);
        })->join(' + ');

        return [
            'ok' => true,
            'sale_id' => $sale->id,
            'status' => $sale->status->value,
            'items_count' => $items->count(),
            'total_amount' => $total,
            'moneda' => $simbolo,
            'desglose' => sprintf(
                'Productos: [%s] = Total %s %.2f',
                $desgloseItems,
                $simbolo,
                $total,
            ),
            'instruccion_para_ia' => 'Pedido guardado. IMPORTANTE: Revisa el último mensaje del cliente. Si el cliente ya te indicó su distrito o ciudad (ej. Trujillo, Piura, Comas), DEBES llamar a la herramienta `consultar_cobertura` INMEDIATAMENTE. NO le vuelvas a preguntar el distrito si ya te lo dio.',
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

        return array_merge($result, [
            'enviado' => true,
            'instruccion_para_ia' => 'La foto ya fue enviada con éxito al cliente con el caption: "'.$caption.'". Tu respuesta de texto final en este turno debe ser extremadamente corta (una sola frase breve) y NO debe repetir el saludo, ni la descripción del producto, ni el caption. Simplemente haz una pregunta corta para continuar la conversación (ej: "¿Qué te parece este color?", "¿Deseas que verifiquemos tu talla?") y no escribas más de una burbuja de texto final.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function ejecutarComprobante(Customer $customer, Message $mensajeEntrante, array $args = []): array
    {

        $resultado = $this->registrarComprobante->handle($customer, $mensajeEntrante, $args);

        return [
            'ok' => true,
            'mensaje_sugerido' => $resultado['mensaje'],
            'sale_status' => $resultado['sale']?->status->value,
            'ia_pausada' => $resultado['pausado'],
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function ejecutarHumano(Customer $customer, array $args, Message $mensajeEntrante): array
    {
        $motivo = (string) ($args['motivo'] ?? 'Atención humana requerida');

        if (! $this->debeEscalarAtencionHumana($mensajeEntrante, $motivo)) {
            Log::info('Escalado a humano bloqueado', [
                'phone' => $customer->phone_number,
                'motivo' => $motivo,
                'message_type' => is_array($mensajeEntrante->metadata) ? ($mensajeEntrante->metadata['type'] ?? 'text') : 'text',
            ]);

            return [
                'ok' => false,
                'ia_pausada' => false,
                'error' => 'No escales: responde tú con la información del prompt (métodos de pago, catálogo, etc.). Los stickers no requieren humano.',
            ];
        }

        $customer->pausarIa($motivo);

        $mensajeTarjeta = MensajesEmpresaDefaults::esperaLinkTarjeta();

        $esTarjeta = str_contains(mb_strtolower($motivo), 'tarjeta');

        if ($esTarjeta) {
            $sale = $customer->activeSale;
            if ($sale !== null) {
                $sale->update([
                    'status' => SaleStatus::PagoPendiente,
                    'payment_method' => 'tarjeta',
                ]);
            }
        }

        return [
            'ok' => true,
            'ia_pausada' => true,
            'motivo' => $motivo,
            'mensaje_sugerido' => $esTarjeta ? $mensajeTarjeta : null,
        ];
    }

    private function debeEscalarAtencionHumana(Message $mensajeEntrante, string $motivo): bool
    {
        $metadata = is_array($mensajeEntrante->metadata) ? $mensajeEntrante->metadata : [];
        $tipo = $metadata['type'] ?? 'text';

        if ($tipo === 'sticker') {
            return false;
        }

        $motivoLower = mb_strtolower(trim($motivo));

        if (str_contains($motivoLower, 'sticker') || str_contains($motivoLower, 'emoji')) {
            return false;
        }

        if (str_contains($motivoLower, 'tarjeta')) {
            return true;
        }

        $motivosQueSiempreEscalan = [
            'comprobante',
            'voucher',
            'captura de pago',
            'verificar pago',
            'confirmar pago',
            'pago recibido',
            'revisar pago',
        ];

        foreach ($motivosQueSiempreEscalan as $patron) {
            if (str_contains($motivoLower, $patron)) {
                return true;
            }
        }

        $motivosResolviblesPorIa = [
            'metodo de pago',
            'método de pago',
            'metodos de pago',
            'métodos de pago',
            'como pago',
            'cómo pago',
            'donde pago',
            'dónde pago',
            'voucher',
            'recibo',
        ];

        foreach ($motivosResolviblesPorIa as $patron) {
            if (str_contains($motivoLower, $patron)) {
                return false;
            }
        }

        return true;
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

        $moneda = VentaConfig::query()->value('moneda')
            ?? CompanySetting::query()->value('moneda')
            ?? 'PEN';
        $simbolo = FormateadorCatalogoProductos::simboloDesdeMoneda((string) $moneda);
        $total = (float) $sale->total_amount;
        $items = $sale->items;

        if ($items->isEmpty()) {
            $desgloseItems = sprintf(
                '%d × %s %s (%.2f)',
                max(1, (int) $sale->quantity),
                $sale->product_name,
                $sale->color ?? '',
                (float) $sale->unit_price
            );
        } else {
            $desgloseItems = $items->map(function ($item) {
                $qty = max(1, (int) $item->quantity);

                return sprintf('%d × %s %s (%.2f)', $qty, $item->product_name, $item->color ?? '', (float) $item->unit_price);
            })->join(' + ');
        }

        return [
            'ok' => true,
            'pedido' => [
                'id' => $sale->id,
                'product_name' => $sale->product_name,
                'color' => $sale->color,
                'size' => NormalizadorStockTallas::etiquetaPublica($sale->size),
                'quantity' => (int) $sale->quantity,
                'unit_price' => (float) $sale->unit_price,
                'items' => $items->map(fn ($i) => [
                    'product_name' => $i->product_name,
                    'color' => $i->color,
                    'size' => NormalizadorStockTallas::etiquetaPublica($i->size),
                    'quantity' => max(1, (int) $i->quantity),
                    'unit_price' => (float) $i->unit_price,
                ])->toArray(),
                'total_amount' => $total,
                'moneda' => $simbolo,
                'desglose' => sprintf(
                    'Productos: [%s] = Total %s %.2f',
                    $desgloseItems,
                    $simbolo,
                    $total,
                ),
                'status' => $sale->status->value,
                'payment_method' => $sale->payment_method,
                'customer_data' => $sale->customer_data ?? [],
                'nota' => 'Productos, colores y cantidades ya confirmados; no volver a pedirlos salvo cambio de la clienta.',
            ],
        ];
    }
}
