<?php

namespace App\Http\Controllers\Api;

use App\Actions\Pedidos\CancelarPedido;
use App\Actions\Pedidos\ConfirmarPagoPedido;
use App\Actions\Pedidos\EnviarLinkPagoTarjeta;
use App\Actions\Pedidos\MarcarPedidoEntregado;
use App\Actions\Pedidos\MarcarPedidoEnviado;
use App\Actions\Pedidos\ReverterPedidoEntregado;
use App\Actions\Pedidos\ReverterPedidoEnviado;
use App\Enums\SaleStatus;
use App\Enums\SaleTransitionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaleTransitionRequest;
use App\Jobs\SendWhatsappMessageJob;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Message;
use App\Models\Sale;
use App\Support\ComprobantePagoMensaje;
use App\Support\MensajesEmpresaDefaults;
use App\Support\MessageBroadcaster;
use App\Support\PipelineKanban;
use App\Support\PlantillaMensajePedido;
use App\Support\ValidadorPlantillaMensaje;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class SaleController extends Controller
{
    /**
     * List sales with pagination and filtering.
     *
     * Query params:
     * - status: Filter by status (pendiente, pagado, etc.)
     * - phone_number: Filter by customer phone
     * - pipeline: boolean - Only show non-cancelled orders
     * - per_page: Items per page (default: 20, max: 100)
     */
    public function index(Request $request): JsonResponse
    {
        $query = Sale::query()
            ->with(['customer', 'product', 'productVariant', 'items'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('phone_number')) {
            $query->where('phone_number', $request->string('phone_number'));
        }

        if ($request->boolean('pipeline')) {
            $scope = $request->string('scope')->toString();

            if ($scope === 'history' || $scope === 'archive') {
                return $this->pipelineArchivo($query, $request);
            }

            return $this->pipelineKanban();
        }

        // Pagination for list views (non-pipeline)
        $perPage = min($request->integer('per_page', 20), 100);
        $sales = $query->paginate($perPage);

        return response()->json($sales);
    }

    private function pipelineKanban(): JsonResponse
    {
        $recentEntregadoIds = PipelineKanban::recentEntregadoIds();
        $recentCanceladoIds = PipelineKanban::recentCanceladoIds();

        $sales = Sale::query()
            ->with(['customer', 'product', 'productVariant', 'items'])
            ->where(function ($query) use ($recentEntregadoIds, $recentCanceladoIds): void {
                $query->whereNotIn('status', [SaleStatus::Entregado->value, SaleStatus::Cancelado->value])
                    ->orWhereIn('id', $recentEntregadoIds)
                    ->orWhereIn('id', $recentCanceladoIds);
            })
            ->oldest()
            ->get();

        return response()->json([
            'sales' => $this->enriquecerParaPipeline($sales),
            'entregados_total' => PipelineKanban::entregadosTotal(),
            'entregados_archived_count' => PipelineKanban::entregadosArchivedCount(),
            'cancelados_total' => PipelineKanban::canceladosTotal(),
            'cancelados_archived_count' => PipelineKanban::canceladosArchivedCount(),
            'hours_limit' => PipelineKanban::HOURS_LIMIT,
        ]);
    }

    private function pipelineArchivo($query, Request $request): JsonResponse
    {
        // For the history view, we might just want to list EVERYTHING terminal, or EVERYTHING delivered.
        // If the frontend explicitly asks for delivered/cancelled, we show them.

        $status = $request->string('history_status')->toString() ?: SaleStatus::Entregado->value;
        $query->where('status', $status);

        $period = $request->string('period')->toString();
        $dateColumn = $status === SaleStatus::Entregado->value ? 'delivered_at' : 'updated_at';

        if ($period === 'hoy') {
            $query->whereDate($dateColumn, today());
        } elseif ($period === 'semana') {
            $query->whereBetween($dateColumn, [now()->startOfWeek(), now()->endOfWeek()]);
        } elseif ($period === 'mes') {
            $query->whereBetween($dateColumn, [now()->startOfMonth(), now()->endOfMonth()]);
        }

        $perPage = min(max($request->integer('per_page', 20), 1), 50);
        $search = trim((string) $request->query('search', ''));

        if ($search !== '') {
            $query->where(function ($inner) use ($search): void {
                $inner->where('phone_number', 'like', "%{$search}%")
                    ->orWhere('product_name', 'like', "%{$search}%");
            });
        }

        $sales = $query
            ->orderByDesc('delivered_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        $sales->getCollection()->transform(function (Sale $sale): array {
            return $this->enriquecerParaPipelineItem($sale, incluirComprobante: false);
        });

        return response()->json($sales);
    }

    public function activeForPhone(string $phoneNumber): JsonResponse
    {
        $customer = Customer::query()->where('phone_number', $phoneNumber)->first();

        if ($customer !== null && $customer->active_sale_id !== null) {
            $sale = Sale::query()
                ->with(['customer', 'product', 'productVariant', 'items'])
                ->find($customer->active_sale_id);

            if ($sale !== null) {
                return response()->json($this->enriquecerSaleSimple($sale));
            }
        }

        $sale = Sale::query()
            ->where('phone_number', $phoneNumber)
            ->whereNotIn('status', [
                SaleStatus::Cancelado,
                SaleStatus::Entregado,
            ])
            ->latest()
            ->with(['customer', 'product', 'productVariant', 'items'])
            ->first();

        return response()->json($sale !== null ? $this->enriquecerSaleSimple($sale) : null);
    }

    /**
     * Add computed permission flags to a single sale for the chat panel.
     *
     * @return array<string, mixed>
     */
    private function enriquecerSaleSimple(Sale $sale): array
    {
        return array_merge($sale->toArray(), [
            'can_confirm_payment' => $sale->puedeVerificarPago(),
            'can_mark_shipped' => $sale->puedeMarcarEnviado(),
            'can_mark_delivered' => $sale->puedeMarcarEntregado(),
            'can_cancel' => $sale->puedeCancelar(),
        ]);
    }

    public function transitionPreview(Sale $sale, Request $request): JsonResponse
    {
        $this->authorize('view', $sale);

        $transition = SaleTransitionType::tryFrom((string) $request->query('transition', ''));
        if ($transition === null) {
            return response()->json(['message' => 'Transición no válida.'], 422);
        }

        $settings = CompanySetting::query()->with('mensajes')->first();
        $sale->loadMissing(['customer', 'items']);

        // Build suggested extra message bubbles based on the transition
        $extraMessages = $this->buildExtraMessages($sale, $transition, $settings);

        return response()->json([
            'transition' => $transition->value,
            'label' => $transition->label(),
            'message' => PlantillaMensajePedido::preview($sale, $transition, $settings),
            'variables' => PlantillaMensajePedido::variablesDisponibles(),
            'sale_summary' => PlantillaMensajePedido::resumenPedido($sale),
            'extra_messages' => $extraMessages,
            'template_invalid' => ValidadorPlantillaMensaje::tieneFormatoIncorrecto(
                match ($transition) {
                    SaleTransitionType::ConfirmPayment => $settings?->mensajes?->pedido_confirmado,
                    SaleTransitionType::MarkShipped => $settings?->mensajes?->pedido_enviado,
                    SaleTransitionType::MarkDelivered => $settings?->mensajes?->pedido_entregado,
                },
            ),
        ]);
    }

    /**
     * Build suggested extra message bubbles for a transition.
     *
     * @return list<array{content: string, delay_seconds: int}>
     */
    private function buildExtraMessages(Sale $sale, SaleTransitionType $transition, ?CompanySetting $settings): array
    {
        $mensajes = $settings?->mensajes;

        if ($transition === SaleTransitionType::ConfirmPayment) {
            // "Pedido por preparar" summary bubble
            $sale->loadMissing('items');

            if ($sale->items->isNotEmpty()) {
                $lineas = $sale->items->map(fn ($item) => "• {$item->quantity}x {$item->product_name}".($item->color ? " ({$item->color})" : ''))->implode("\n");
            } else {
                $lineas = "• {$sale->quantity}x {$sale->product_name}".($sale->color ? " ({$sale->color})" : '');
            }

            $total = number_format((float) $sale->total_amount, 2);
            $content = "📦 *Pedido por preparar*\n{$lineas}\n• Total: S/ {$total}";

            return [['content' => $content, 'delay_seconds' => 3]];
        }

        if ($transition === SaleTransitionType::MarkShipped) {
            $tipoEnvio = is_array($sale->customer_data) ? ($sale->customer_data['tipo_envio'] ?? null) : null;

            if ($tipoEnvio === 'shalom') {
                $recordatorio = $mensajes?->recordatorio_shalom
                    ?: MensajesEmpresaDefaults::recordatorioShalom();
            } else {
                // Default to motorizado reminder for any delivery type
                $recordatorio = $mensajes?->recordatorio_motorizado
                    ?: MensajesEmpresaDefaults::recordatorioMotorizado();
            }

            return [['content' => $recordatorio, 'delay_seconds' => 5]];
        }

        return [];
    }

    public function confirmPayment(Sale $sale, SaleTransitionRequest $request, ConfirmarPagoPedido $confirmarPago): JsonResponse
    {
        $this->authorize('confirmPayment', $sale);

        if (! $sale->puedeVerificarPago()) {
            return response()->json([
                'message' => 'Este pedido no puede confirmarse en su estado actual.',
            ], 422);
        }

        $settings = CompanySetting::query()->with('mensajes')->first();
        $sale->loadMissing(['customer', 'items']);

        $bubbles = $request->messageBubbles();
        $mainMessage = $bubbles[0]['content'] ?? PlantillaMensajePedido::preview($sale, SaleTransitionType::ConfirmPayment, $settings);
        $extraMessages = array_slice($bubbles, 1);

        try {
            $sale = $confirmarPago->handle(
                $sale,
                $request->user(),
                $mainMessage,
                $extraMessages,
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json($sale);
    }

    public function sendPaymentLink(Sale $sale, Request $request, EnviarLinkPagoTarjeta $enviarLink): JsonResponse
    {
        $this->authorize('update', $sale);

        $validated = $request->validate([
            'link' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $resultado = $enviarLink->handle($sale, $validated['link']);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'link' => $resultado['link'],
            'message' => $resultado['message'],
            'sale' => $sale->fresh(['customer', 'product', 'productVariant', 'items']),
        ]);
    }

    public function sendPaymentReminder(Sale $sale): JsonResponse
    {
        $this->authorize('update', $sale);

        if (! in_array($sale->status, [SaleStatus::PagoPendiente, SaleStatus::Confirmado])) {
            return response()->json(['message' => 'Solo se pueden enviar recordatorios a pedidos pendientes de pago o confirmación.'], 422);
        }

        $customer = $sale->customer;

        if ($customer && ! $customer->ia_paused) {
            $customer->pausarIa('Pausado por recordatorio de pago automático.');
        }

        $monto = number_format((float) $sale->total_amount, 2);
        $producto = $sale->product_name;

        $nombreCorto = '';
        if ($customer && $customer->name) {
            $nombreCorto = ' '.explode(' ', $customer->name)[0];
        }

        $content = "Hola{$nombreCorto}, notamos que tienes un pedido pendiente por S/ {$monto} de *{$producto}*. ¿Te ayudamos con algo para finalizar tu compra?";

        $message = Message::create([
            'message_id' => 'temp_'.uniqid(),
            'phone_number' => $sale->phone_number,
            'content' => $content,
            'direction' => 'outgoing',
            'status' => 'pending',
            'whatsapp_timestamp' => now(),
            'metadata' => ['type' => 'text'],
        ]);

        MessageBroadcaster::broadcast($message, 'SaleController');
        SendWhatsappMessageJob::dispatchSync($message);
        $message->refresh();

        return response()->json([
            'message' => 'Recordatorio enviado correctamente',
            'data' => $message,
        ]);
    }

    public function markShipped(Sale $sale, SaleTransitionRequest $request, MarcarPedidoEnviado $marcarEnviado): JsonResponse
    {
        $this->authorize('markShipped', $sale);

        if (! $sale->puedeMarcarEnviado()) {
            return response()->json([
                'message' => 'Solo se pueden enviar pedidos confirmados.',
            ], 422);
        }

        $bubbles = $request->messageBubbles();
        $extraMessages = array_slice($bubbles, 1);

        try {
            $imageUrl = $this->resolveTransitionImageUrl($request);
            $sale = $marcarEnviado->handle($sale, $request->validated('message'), $imageUrl, $extraMessages);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json($sale);
    }

    public function markDelivered(Sale $sale, SaleTransitionRequest $request, MarcarPedidoEntregado $marcarEntregado): JsonResponse
    {
        $this->authorize('markDelivered', $sale);

        if (! $sale->puedeMarcarEntregado()) {
            return response()->json([
                'message' => 'Solo se pueden entregar pedidos enviados.',
            ], 422);
        }

        try {
            $sale = $marcarEntregado->handle($sale, $request->validated('message'));
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json($sale);
    }

    public function revertDelivered(Sale $sale, ReverterPedidoEntregado $reverter): JsonResponse
    {
        $this->authorize('markDelivered', $sale);

        try {
            $sale = $reverter->handle($sale);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json($sale);
    }

    public function revertShipped(Sale $sale, ReverterPedidoEnviado $reverter): JsonResponse
    {
        $this->authorize('markShipped', $sale);

        try {
            $sale = $reverter->handle($sale);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json($sale);
    }

    public function cancel(Sale $sale, CancelarPedido $cancelar): JsonResponse
    {
        $this->authorize('update', $sale);

        if (! $sale->puedeCancelar()) {
            return response()->json([
                'message' => 'Este pedido no puede cancelarse en su estado actual.',
            ], 422);
        }

        try {
            $sale = $cancelar->handle($sale);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json($sale);
    }

    /**
     * Enrich a single sale for pipeline view (used with pagination).
     *
     * @return array<string, mixed>
     */
    private function enriquecerParaPipelineItem(Sale $sale, bool $incluirComprobante = true): array
    {
        $enriched = $this->enriquecerParaPipeline(collect([$sale]), $incluirComprobante);

        return $enriched[0];
    }

    /**
     * @param  Collection<int, Sale>  $sales
     * @return list<array<string, mixed>>
     */
    private function enriquecerParaPipeline(Collection $sales, bool $incluirComprobante = true): array
    {
        if ($sales->isEmpty()) {
            return [];
        }

        $phones = $sales->pluck('phone_number')->unique()->values();
        $saleIds = $sales->pluck('id');

        $mensajesPorId = collect();
        $etiquetadosPorVenta = collect();
        $imagenesEntradaPorTelefono = collect();

        $necesitaComprobante = $incluirComprobante && $sales->contains(
            fn (Sale $sale): bool => in_array($sale->status, [SaleStatus::PagoRecibido, SaleStatus::PagoPendiente], true)
        );

        if ($necesitaComprobante) {
            $comprobanteMessageIds = $sales
                ->map(function (Sale $sale): ?int {
                    $id = ($sale->agent_metadata ?? [])['comprobante_message_id'] ?? null;

                    return is_numeric($id) ? (int) $id : null;
                })
                ->filter()
                ->unique()
                ->values();

            $mensajesPorId = Message::query()
                ->whereIn('id', $comprobanteMessageIds)
                ->get()
                ->keyBy('id');

            $etiquetadosPorVenta = Message::query()
                ->where('direction', 'incoming')
                ->where('metadata->is_payment_comprobante', true)
                ->whereIn('metadata->sale_id', $saleIds)
                ->orderByDesc('created_at')
                ->get()
                ->groupBy(fn (Message $mensaje): int => (int) ($mensaje->metadata['sale_id'] ?? 0));

            $telefonosConPago = $sales
                ->filter(fn (Sale $sale): bool => in_array($sale->status, [SaleStatus::PagoRecibido, SaleStatus::PagoPendiente], true))
                ->pluck('phone_number')
                ->unique()
                ->values();

            if ($telefonosConPago->isNotEmpty()) {
                $imagenesEntradaPorTelefono = Message::query()
                    ->whereIn('phone_number', $telefonosConPago)
                    ->where('direction', 'incoming')
                    ->where(function ($query): void {
                        $query->where('metadata->type', 'image')
                            ->orWhere('metadata->whatsapp_message_type', 'image')
                            ->orWhereNotNull('metadata->image_url');
                    })
                    ->orderByDesc('created_at')
                    ->limit(200)
                    ->get()
                    ->groupBy('phone_number');
            }
        }

        return $sales->map(function (Sale $sale) use ($mensajesPorId, $etiquetadosPorVenta, $imagenesEntradaPorTelefono): array {
            $imageUrl = ComprobantePagoMensaje::resolverParaPedido(
                $sale,
                $mensajesPorId,
                $etiquetadosPorVenta,
                $imagenesEntradaPorTelefono,
            );

            return array_merge($sale->toArray(), [
                'customer_name' => $sale->customer->name ?? '',
                'delivery_address' => '',
                'maps_url' => '',
                'comprobante_url' => $imageUrl,
                'can_confirm_payment' => $sale->puedeVerificarPago(),
                'can_mark_shipped' => $sale->puedeMarcarEnviado(),
                'can_mark_delivered' => $sale->puedeMarcarEntregado(),
                'can_cancel' => $sale->puedeCancelar(),
            ]);
        })->all();
    }

    private function resolveTransitionImageUrl(SaleTransitionRequest $request): ?string
    {
        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('shipping-receipts', 'public');
            $relativeUrl = Storage::disk('public')->url($path);
            $publicBase = rtrim((string) config('app.public_url', config('app.url')), '/');

            if (str_starts_with($relativeUrl, 'http://') || str_starts_with($relativeUrl, 'https://')) {
                return $relativeUrl;
            }

            return $publicBase.'/'.ltrim($relativeUrl, '/');
        }

        $imageUrl = $request->validated('image_url');

        return is_string($imageUrl) && $imageUrl !== '' ? $imageUrl : null;
    }
}
