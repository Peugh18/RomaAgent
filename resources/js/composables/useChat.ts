import { computed, ref, watch } from 'vue';
import type { ChatConversation, ChatMessage } from '@/types/chat';
import { apiJson } from '@/composables/useApi';
import { useChatRealtime } from '@/composables/useChatRealtime';

export function useChat() {
    const selectedPhone = ref<string | null>(null);
    const newMessage = ref('');
    const messages = ref<ChatMessage[]>([]);
    const conversations = ref<ChatConversation[]>([]);
    const loading = ref(false);
    const sending = ref(false);
    const resendingId = ref<number | null>(null);
    const sendError = ref<string | null>(null);
    const messagesContainer = ref<HTMLElement | null>(null);

    const filteredMessages = computed(() => {
        if (!selectedPhone.value) {
            return [];
        }

        return messages.value.filter((message) => message.phone_number === selectedPhone.value);
    });

    const scrollToBottom = () => {
        requestAnimationFrame(() => {
            const el = messagesContainer.value;
            if (el) {
                el.scrollTop = el.scrollHeight;
            }
        });
    };

    const isOptimisticMessage = (message: ChatMessage): boolean =>
        String(message.message_id).startsWith('temp_') || message.id > 1_000_000_000_000;

    const findMessageIndex = (incoming: ChatMessage): number => {
        const byIdentifier = messages.value.findIndex(
            (message) => message.id === incoming.id || message.message_id === incoming.message_id,
        );

        if (byIdentifier >= 0) {
            return byIdentifier;
        }

        if (incoming.direction !== 'outgoing') {
            return -1;
        }

        return messages.value.findLastIndex(
            (message) =>
                isOptimisticMessage(message) &&
                message.direction === 'outgoing' &&
                message.phone_number === incoming.phone_number &&
                message.content === incoming.content &&
                (message.status === 'pending' || message.status === incoming.status),
        );
    };

    const upsertMessage = (incoming: ChatMessage) => {
        const existingIndex = findMessageIndex(incoming);

        if (existingIndex >= 0) {
            messages.value[existingIndex] = incoming;
        } else {
            messages.value.push(incoming);
        }

        const convIndex = conversations.value.findIndex((c) => c.phone === incoming.phone_number);
        const preview = {
            phone: incoming.phone_number,
            name: incoming.customer_name,
            last_message: incoming.content,
            last_at: incoming.created_at,
            direction: incoming.direction,
            status: incoming.status,
        };

        if (convIndex >= 0) {
            conversations.value[convIndex] = preview;
            conversations.value.sort(
                (a, b) => new Date(b.last_at ?? 0).getTime() - new Date(a.last_at ?? 0).getTime(),
            );
        } else {
            conversations.value.unshift(preview);
        }

        if (!selectedPhone.value) {
            selectedPhone.value = incoming.phone_number;
        }

        scrollToBottom();
    };

    const fetchConversations = async () => {
        conversations.value = await apiJson<ChatConversation[]>('/api/conversations');
    };

    const fetchMessagesForPhone = async (phone: string) => {
        messages.value = await apiJson<ChatMessage[]>(
            `/api/messages?phone_number=${encodeURIComponent(phone)}&limit=200`,
        );
    };

    const fetchMessages = async () => {
        loading.value = true;
        try {
            await fetchConversations();

            if (!selectedPhone.value && conversations.value.length > 0) {
                selectedPhone.value = conversations.value[0].phone;
            }

            if (selectedPhone.value) {
                await fetchMessagesForPhone(selectedPhone.value);
            } else {
                messages.value = [];
            }
        } finally {
            loading.value = false;
            scrollToBottom();
        }
    };

    const selectConversation = async (phone: string) => {
        selectedPhone.value = phone;
        loading.value = true;
        try {
            await fetchMessagesForPhone(phone);
        } finally {
            loading.value = false;
            scrollToBottom();
        }
    };

    const sendMessage = async () => {
        if (!selectedPhone.value || !newMessage.value.trim() || sending.value) {
            return;
        }

        sending.value = true;
        sendError.value = null;

        const content = newMessage.value.trim();
        newMessage.value = '';

        const optimistic: ChatMessage = {
            id: Date.now(),
            message_id: `temp_${Date.now()}`,
            phone_number: selectedPhone.value,
            customer_name: null,
            content,
            direction: 'outgoing',
            status: 'pending',
            whatsapp_timestamp: null,
            metadata: { type: 'text' },
            created_at: new Date().toISOString(),
            updated_at: new Date().toISOString(),
        };

        messages.value.push(optimistic);
        scrollToBottom();

        try {
            const result = await apiJson<{ data: ChatMessage }>('/api/send-message', {
                method: 'POST',
                body: JSON.stringify({
                    phone_number: selectedPhone.value,
                    content,
                }),
            });

            upsertMessage(result.data);
        } catch (error) {
            sendError.value = error instanceof Error ? error.message : 'No se pudo enviar';
            const index = messages.value.findIndex((m) => m.message_id === optimistic.message_id);
            if (index >= 0) {
                messages.value[index] = {
                    ...optimistic,
                    status: 'failed',
                    metadata: { send_error: sendError.value },
                };
            }
        } finally {
            sending.value = false;
        }
    };

    const resendMessage = async (message: ChatMessage) => {
        if (resendingId.value !== null) {
            return;
        }

        resendingId.value = message.id;
        sendError.value = null;

        try {
            const result = await apiJson<{ data: ChatMessage }>(`/api/messages/${message.id}/resend`, {
                method: 'POST',
            });

            upsertMessage(result.data);
        } catch (error) {
            sendError.value = error instanceof Error ? error.message : 'No se pudo reenviar';
        } finally {
            resendingId.value = null;
        }
    };

    watch(selectedPhone, () => scrollToBottom());

    useChatRealtime({
        onPoll: fetchMessages,
        onMessageReceived: upsertMessage,
    });

    return {
        selectedPhone,
        newMessage,
        messages,
        conversations,
        filteredMessages,
        loading,
        sending,
        resendingId,
        sendError,
        messagesContainer,
        fetchMessages,
        selectConversation,
        sendMessage,
        resendMessage,
    };
}
