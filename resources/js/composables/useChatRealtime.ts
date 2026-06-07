import { onMounted, onUnmounted } from 'vue';
import type { ChatMessage } from '@/types/chat';

interface ChatRealtimeHandlers {
    onInitialLoad: () => void | Promise<void>;
    onPoll: () => void | Promise<void>;
    onMessageReceived?: (message: ChatMessage) => void;
}

export function useChatRealtime(handlers: ChatRealtimeHandlers) {
    let echo: {
        private: (channel: string) => {
            listen: (event: string, callback: (payload: { message: ChatMessage }) => void) => void;
        };
        leave: (channel: string) => void;
    } | null = null;
    let pollingInterval: ReturnType<typeof setInterval> | null = null;
    let echoActive = false;

    onMounted(async () => {
        await handlers.onInitialLoad();

        if (typeof window !== 'undefined' && (window as Window & { Echo?: unknown }).Echo) {
            try {
                echo = (window as Window & { Echo: NonNullable<typeof echo> }).Echo;
                echoActive = true;

                echo.private('crm.messages').listen('.message.received', (payload: { message: ChatMessage }) => {
                    if (handlers.onMessageReceived) {
                        handlers.onMessageReceived(payload.message);
                    } else {
                        void handlers.onPoll();
                    }
                });
            } catch (error) {
                console.error('Error configuring Echo:', error);
            }
        }

        const pollMs = echoActive ? 90_000 : 45_000;
        pollingInterval = setInterval(() => {
            void handlers.onPoll();
        }, pollMs);
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
