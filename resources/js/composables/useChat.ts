import { computed, ref, watch } from 'vue';
import type { ChatConversation, ChatMessage } from '@/types/chat';
import { apiJson } from '@/composables/useApi';
import { useChatRealtime } from '@/composables/useChatRealtime';

interface UseChatOptions {
    initialPhone?: string | null;
}

export function useChat(options: UseChatOptions = {}) {
    const selectedPhone = ref<string | null>(options.initialPhone ?? null);
    const newMessage = ref('');
    const selectedImage = ref<File | null>(null);
    const imagePreviewUrl = ref<string | null>(null);
    const messages = ref<ChatMessage[]>([]);
    const conversations = ref<ChatConversation[]>([]);
    const loading = ref(false);
    const sending = ref(false);
    const resendingId = ref<number | null>(null);
    const sendError = ref<string | null>(null);
    const loadError = ref<string | null>(null);
    const messagesContainer = ref<HTMLElement | null>(null);

    const filteredMessages = computed(() => {
        if (!selectedPhone.value) {
            return [];
        }

        return messages.value.filter((message) => message.phone_number === selectedPhone.value);
    });

    const clearImage = () => {
        if (imagePreviewUrl.value) {
            URL.revokeObjectURL(imagePreviewUrl.value);
            imagePreviewUrl.value = null;
        }
        selectedImage.value = null;
    };

    const setImage = (file: File | null) => {
        clearImage();
        if (file) {
            selectedImage.value = file;
            imagePreviewUrl.value = URL.createObjectURL(file);
        }
    };

    const scrollToBottom = () => {
        requestAnimationFrame(() => {
            const el = messagesContainer.value;
            if (el) {
                el.scrollTop = el.scrollHeight;
            }
        });
    };

    const isNearBottom = (): boolean => {
        const el = messagesContainer.value;
        if (!el) {
            return true;
        }

        return el.scrollHeight - el.scrollTop - el.clientHeight < 120;
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

    const initialLoad = async () => {
        loading.value = true;
        loadError.value = null;

        try {
            await fetchConversations();

            const phoneFromUrl = options.initialPhone?.trim() || null;
            if (phoneFromUrl) {
                selectedPhone.value = phoneFromUrl;
            } else if (!selectedPhone.value && conversations.value.length > 0) {
                selectedPhone.value = conversations.value[0].phone;
            }

            if (selectedPhone.value) {
                await fetchMessagesForPhone(selectedPhone.value);
            } else {
                messages.value = [];
            }
        } catch (error) {
            loadError.value = error instanceof Error ? error.message : 'No se pudo cargar el chat';
        } finally {
            loading.value = false;
            scrollToBottom();
        }
    };

    const pollUpdate = async () => {
        try {
            await fetchConversations();

            if (!selectedPhone.value) {
                return;
            }

            const stickToBottom = isNearBottom();
            await fetchMessagesForPhone(selectedPhone.value);

            if (stickToBottom) {
                scrollToBottom();
            }
        } catch {
            // Polling silencioso: no interrumpir al operador
        }
    };

    const selectConversation = async (phone: string) => {
        selectedPhone.value = phone;
        loading.value = true;
        loadError.value = null;

        try {
            await fetchMessagesForPhone(phone);
        } catch (error) {
            loadError.value = error instanceof Error ? error.message : 'No se pudo cargar los mensajes';
        } finally {
            loading.value = false;
            scrollToBottom();
        }
    };

    const sendMessage = async () => {
        if (!selectedPhone.value || (!newMessage.value.trim() && !selectedImage.value) || sending.value) {
            return;
        }

        sending.value = true;
        sendError.value = null;

        const content = newMessage.value.trim();
        const optimisticImageUrl = imagePreviewUrl.value;
        const hasImage = selectedImage.value !== null;
        const fileToSend = selectedImage.value; // Store reference

        newMessage.value = '';
        selectedImage.value = null;
        imagePreviewUrl.value = null;

        const metadata: any = { type: hasImage ? 'image' : 'text' };
        if (hasImage) {
            metadata.image_url = optimisticImageUrl;
            if (content) {
                metadata.image_caption = content;
            }
        }

        const optimistic: ChatMessage = {
            id: Date.now(),
            message_id: `temp_${Date.now()}`,
            phone_number: selectedPhone.value,
            customer_name: null,
            content,
            direction: 'outgoing',
            status: 'pending',
            whatsapp_timestamp: null,
            metadata,
            created_at: new Date().toISOString(),
            updated_at: new Date().toISOString(),
        };

        messages.value.push(optimistic);
        scrollToBottom();

        try {
            let body: string | FormData;
            if (hasImage && fileToSend) {
                body = new FormData();
                body.append('phone_number', selectedPhone.value);
                if (content) {
                    body.append('content', content);
                }
                body.append('image', fileToSend);
            } else {
                body = JSON.stringify({
                    phone_number: selectedPhone.value,
                    content,
                });
            }

            const result = await apiJson<{ data: ChatMessage }>('/api/send-message', {
                method: 'POST',
                body,
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

    const handleMessageReceived = async (incoming: ChatMessage) => {
        upsertMessage(incoming);
        try {
            await fetchConversations();
        } catch {
            // Actualización silenciosa del estado ia_paused
        }
    };

    useChatRealtime({
        onInitialLoad: initialLoad,
        onPoll: pollUpdate,
        onMessageReceived: handleMessageReceived,
    });
    return {
        selectedPhone,
        newMessage,
        selectedImage,
        imagePreviewUrl,
        messages,
        conversations,
        filteredMessages,
        loading,
        sending,
        resendingId,
        sendError,
        loadError,
        messagesContainer,
        setImage,
        clearImage,
        initialLoad,
        pollUpdate,
        selectConversation,
        sendMessage,
        resendMessage,
    };
}
