import { onMounted, onUnmounted } from 'vue';
import type { ChatMessage } from '@/types/chat';

interface ChatRealtimeHandlers {
    onPoll: () => void;
    onMessageReceived: (message: ChatMessage) => void;
}

export function useChatRealtime(handlers: ChatRealtimeHandlers) {
     
    let echo: any = null;
    let pollingInterval: ReturnType<typeof setInterval> | null = null;

    onMounted(() => {
        handlers.onPoll();

        pollingInterval = setInterval(handlers.onPoll, 30_000);

        if (typeof window !== 'undefined' && (window as Window & { Echo?: unknown }).Echo) {
            try {
                echo = (window as Window & { Echo: typeof echo }).Echo;

                echo.private('crm.messages').listen('.message.received', (e: { message: ChatMessage }) => {
                    handlers.onMessageReceived(e.message);
                });
            } catch (error) {
                console.error('Error configuring Echo:', error);
            }
        }
    });

    onUnmounted(() => {
        if (echo) {
            echo.leave('crm.messages');
        }
        if (pollingInterval) {
            clearInterval(pollingInterval);
        }
    });
}
