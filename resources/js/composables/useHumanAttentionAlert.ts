import { computed, ref, watch, type Ref } from 'vue';
import type { ChatConversation } from '@/types/chat';

const VIBRATE_PATTERN = [200, 100, 200, 100, 300];

export function notifyHumanAttention(): void {
    if (typeof navigator !== 'undefined' && 'vibrate' in navigator) {
        navigator.vibrate(VIBRATE_PATTERN);
    }
}

export function useHumanAttentionAlert(conversations: Ref<ChatConversation[]>) {
    const knownPausedPhones = ref<Set<string>>(new Set());
    const showAlert = ref(false);
    const latestAlertPhone = ref<string | null>(null);
    const initialized = ref(false);

    const humanAttentionChats = computed(() =>
        conversations.value.filter((conversation) => conversation.ia_paused),
    );

    const humanAttentionCount = computed(() => humanAttentionChats.value.length);

    watch(
        conversations,
        (list) => {
            const paused = list.filter((conversation) => conversation.ia_paused);

            if (!initialized.value) {
                knownPausedPhones.value = new Set(paused.map((conversation) => conversation.phone));
                initialized.value = true;

                return;
            }

            for (const conversation of paused) {
                if (!knownPausedPhones.value.has(conversation.phone)) {
                    knownPausedPhones.value.add(conversation.phone);
                    latestAlertPhone.value = conversation.phone;
                    showAlert.value = true;
                    notifyHumanAttention();
                }
            }

            const currentPaused = new Set(paused.map((conversation) => conversation.phone));

            for (const phone of knownPausedPhones.value) {
                if (!currentPaused.has(phone)) {
                    knownPausedPhones.value.delete(phone);
                }
            }

            if (humanAttentionCount.value === 0) {
                showAlert.value = false;
                latestAlertPhone.value = null;
            }
        },
        { deep: true },
    );

    const dismissAlert = (): void => {
        showAlert.value = false;
    };

    return {
        humanAttentionChats,
        humanAttentionCount,
        showAlert,
        latestAlertPhone,
        dismissAlert,
    };
}
