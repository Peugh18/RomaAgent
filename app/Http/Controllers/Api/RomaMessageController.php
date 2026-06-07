<?php

namespace App\Http\Controllers\Api;

use App\Actions\Mensajes\ReenviarMensajeWhatsapp;
use App\Actions\ProcessIncomingMessage;
use App\Actions\UpdateMessageStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMessageRequest;
use App\Jobs\SendWhatsappMessageJob;
use App\Models\Customer;
use App\Models\Message;
use App\Support\AutenticacionWebhookRoma;
use App\Support\MessageBroadcaster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class RomaMessageController extends Controller
{
    public function __construct(
        private ProcessIncomingMessage $processIncoming,
        private UpdateMessageStatus $updateStatus,
        private ReenviarMensajeWhatsapp $reenviarMensaje,
    ) {}

    public function receive(Request $request): JsonResponse
    {
        if (! AutenticacionWebhookRoma::verify($request)) {
            Log::warning('Roma inbound: auth failed', ['ip' => $request->ip()]);

            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $payload = $request->all();

        if ($this->processIncoming->isStatusUpdate($payload)) {
            return $this->handleStatusUpdate($payload);
        }

        try {
            $message = $this->processIncoming->execute($payload);

            return response()->json([
                'message' => 'Message received',
                'data' => $message,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function index(Request $request): JsonResponse
    {
        $messages = $this->fetchMessages(
            phoneNumber: $request->query('phone_number'),
            limit: (int) $request->query('limit', 200)
        );

        return response()->json($messages);
    }

    private function fetchMessages(?string $phoneNumber, int $limit): Collection
    {
        $query = Message::query()->orderBy('created_at', 'desc');

        if (is_string($phoneNumber) && $phoneNumber !== '') {
            $query->where('phone_number', $phoneNumber);
        }

        return $query->limit(min($limit, 500))->get()->reverse()->values();
    }

    public function conversations(): JsonResponse
    {
        $conversations = $this->fetchConversations();

        return response()->json($conversations);
    }

    private function fetchConversations(): Collection
    {
        $latestIds = Message::query()
            ->selectRaw('MAX(id) as id')
            ->groupBy('phone_number')
            ->pluck('id');

        $messages = Message::query()
            ->whereIn('id', $latestIds)
            ->orderByDesc('created_at')
            ->get();

        $customersByPhone = $this->loadCustomersByPhone($messages);

        return $messages
            ->map(fn (Message $message): array => [
                'phone' => $message->phone_number,
                'name' => $message->customer_name,
                'last_message' => $message->content,
                'last_at' => $message->created_at?->toIso8601String(),
                'direction' => $message->direction,
                'status' => $message->status,
                ...$this->customerMetaFromMap($message->phone_number, $customersByPhone),
            ])->values();
    }

    /**
     * @param  Collection<int, Message>  $messages
     * @return Collection<string, Customer>
     */
    private function loadCustomersByPhone(Collection $messages): Collection
    {
        $phones = $messages->pluck('phone_number')->unique()->filter()->values();

        if ($phones->isEmpty()) {
            return collect();
        }

        return Customer::query()
            ->whereIn('phone_number', $phones)
            ->with('activeSale')
            ->get()
            ->keyBy('phone_number');
    }

    /**
     * @param  Collection<string, Customer>  $customersByPhone
     * @return array{ia_paused: bool, ia_pause_reason: string|null, pending_payment: bool, active_sale_status: string|null}
     */
    private function customerMetaFromMap(string $phoneNumber, Collection $customersByPhone): array
    {
        $customer = $customersByPhone->get($phoneNumber);

        if ($customer === null) {
            return [
                'ia_paused' => false,
                'ia_pause_reason' => null,
                'pending_payment' => false,
                'active_sale_status' => null,
            ];
        }

        return $this->customerMetaFromModel($customer);
    }

    /**
     * @return array{ia_paused: bool, ia_pause_reason: string|null, pending_payment: bool, active_sale_status: string|null}
     */
    private function customerMetaFromModel(Customer $customer): array
    {
        $activeSale = $customer->activeSale;
        $status = $activeSale?->status?->value;

        return [
            'ia_paused' => (bool) $customer->ia_paused,
            'ia_pause_reason' => $customer->ia_pause_reason,
            'pending_payment' => $activeSale !== null && $activeSale->puedeVerificarPago(),
            'active_sale_status' => $status,
        ];
    }

    public function send(StoreMessageRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $customer = Customer::query()
            ->where('phone_number', $validated['phone_number'])
            ->first();

        if ($customer !== null && ! $customer->ia_paused) {
            return response()->json([
                'message' => 'La IA está activa para esta clienta. Cambia a modo Humano para escribir manualmente.',
            ], 422);
        }

        try {
            $message = Message::create([
                'message_id' => 'temp_'.uniqid(),
                'phone_number' => $validated['phone_number'],
                'content' => $validated['content'],
                'direction' => 'outgoing',
                'status' => 'pending',
                'whatsapp_timestamp' => now(),
                'metadata' => ['type' => 'text'],
            ]);

            MessageBroadcaster::broadcast($message, 'RomaMessageController');
            SendWhatsappMessageJob::dispatch($message);

            return response()->json([
                'message' => 'Message queued for sending',
                'data' => $message,
            ]);
        } catch (\Throwable $e) {
            Log::error('Roma send: queue failed', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Failed to queue message',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function resend(Message $message): JsonResponse
    {
        $customer = Customer::query()
            ->where('phone_number', $message->phone_number)
            ->first();

        if ($customer !== null && ! $customer->ia_paused) {
            return response()->json([
                'message' => 'La IA está activa para esta clienta. Cambia a modo Humano para reenviar mensajes manuales.',
            ], 422);
        }

        try {
            $reenviado = $this->reenviarMensaje->handle($message);

            return response()->json([
                'message' => 'Mensaje reencolado para reenvío',
                'data' => $reenviado,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('Roma resend: failed', [
                'id' => $message->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'No se pudo reenviar el mensaje',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function handleStatusUpdate(array $payload): JsonResponse
    {
        $message = $this->updateStatus->execute($payload);

        if (! $message) {
            return response()->json(['message' => 'Status event ignored'], 200);
        }

        return response()->json([
            'message' => 'Status updated',
            'wa_id' => $message->message_id,
            'status' => $message->status,
        ]);
    }
}
