<?php

namespace App\Http\Controllers\Api;

use App\Actions\Mensajes\EnviarMensajeWhatsappSaliente;
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
            ->map(function (Message $message) use ($customersByPhone): array {
                $customer = $customersByPhone->get($message->phone_number);
                $name = ($customer && ! empty($customer->name)) ? $customer->name : $message->customer_name;

                return [
                    'phone' => $message->phone_number,
                    'name' => $name,
                    'last_message' => $message->content,
                    'last_at' => $message->created_at?->toIso8601String(),
                    'direction' => $message->direction,
                    'status' => $message->status,
                    ...$this->customerMetaFromMap($message->phone_number, $customersByPhone),
                ];
            })->values();
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
            ->with(['activeSale', 'labels'])
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
                'labels' => [],
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
            'labels' => $customer->labels->map(fn ($l) => ['id' => $l->id, 'name' => $l->name, 'color' => $l->color])->toArray(),
        ];
    }

    public function send(StoreMessageRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $imageUrl = null;
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('messages/outbound', 'public');
            $imageUrl = asset('storage/'.$path);
        }

        $customer = Customer::query()
            ->where('phone_number', $validated['phone_number'])
            ->first();

        if ($customer !== null && ! $customer->ia_paused) {
            return response()->json([
                'message' => 'La IA está activa para esta clienta. Cambia a modo Humano para escribir manualmente.',
            ], 422);
        }

        try {
            $metadata = ['type' => $imageUrl ? 'image' : 'text'];
            if ($imageUrl !== null) {
                $metadata['image_url'] = $imageUrl;
                if (! empty($validated['content'])) {
                    $metadata['image_caption'] = $validated['content'];
                }
            }

            $message = Message::create([
                'message_id' => 'temp_'.uniqid(),
                'phone_number' => $validated['phone_number'],
                'content' => $validated['content'] ?? '',
                'direction' => 'outgoing',
                'status' => 'pending',
                'whatsapp_timestamp' => now(),
                'metadata' => $metadata,
            ]);

            MessageBroadcaster::broadcast($message, 'RomaMessageController');
            SendWhatsappMessageJob::dispatchSync($message);
            $message->refresh();

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

    public function pin(Message $message): JsonResponse
    {
        $willPin = ! $message->is_pinned;

        // Unpin any previously pinned messages in the same conversation
        if ($willPin) {
            Message::query()
                ->where('phone_number', $message->phone_number)
                ->where('is_pinned', true)
                ->where('id', '!=', $message->id)
                ->update(['is_pinned' => false]);
        }

        $message->update(['is_pinned' => $willPin]);

        return response()->json($message->fresh());
    }

    public function sendSystem(Request $request, EnviarMensajeWhatsappSaliente $enviar): JsonResponse
    {
        $validated = $request->validate([
            'phone_number' => ['required', 'string'],
            'content' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $message = $enviar->handle(
                phoneNumber: $validated['phone_number'],
                content: $validated['content'],
                metadataExtra: ['generated_by' => 'label_assignment'],
            );

            return response()->json(['data' => $message]);
        } catch (\Throwable $e) {
            Log::error('Roma sendSystem: failed', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'No se pudo enviar el mensaje'], 500);
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
