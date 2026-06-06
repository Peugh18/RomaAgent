<script setup lang="ts">
import ChatComposer from '@/components/chat/ChatComposer.vue';
import ChatConversationList from '@/components/chat/ChatConversationList.vue';
import ChatMessageBubble from '@/components/chat/ChatMessageBubble.vue';
import ChatSalePanel from '@/components/chat/ChatSalePanel.vue';
import ChatThreadHeader from '@/components/chat/ChatThreadHeader.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useChat } from '@/composables/useChat';
import { useActiveSale } from '@/composables/useSale';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import { Loader2 } from 'lucide-vue-next';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Chat WhatsApp', href: '/chat' }];

const {
    selectedPhone,
    newMessage,
    conversations,
    filteredMessages,
    loading,
    sending,
    resendingId,
    sendError,
    messagesContainer,
    selectConversation,
    sendMessage,
    resendMessage,
} = useChat();

const phoneRef = computed(() => selectedPhone.value);

const {
    sale: activeSale,
    loading: saleLoading,
    confirming: confirmingPayment,
    error: saleError,
    loadActiveSale,
    confirmPayment,
} = useActiveSale(phoneRef);

const activeConversation = computed(() =>
    conversations.value.find((conversation) => conversation.phone === selectedPhone.value),
);

const onConfirmPayment = async () => {
    const ok = await confirmPayment();
    if (ok) {
        await loadActiveSale();
    }
};

watch(selectedPhone, () => {
    void loadActiveSale();
});
</script>

<template>
    <Head title="Chat WhatsApp" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-[calc(100vh-5rem)] min-h-[32rem] flex-col gap-4 overflow-hidden lg:flex-row lg:gap-0 lg:rounded-xl lg:border lg:border-border lg:shadow-sm"
        >
            <ChatConversationList
                :conversations="conversations"
                :selected-phone="selectedPhone"
                :loading="loading"
                @select="selectConversation"
            />

            <section class="flex min-w-0 flex-1 flex-col overflow-hidden bg-[#efeae2] dark:bg-muted/20 lg:rounded-r-xl">
                <ChatThreadHeader
                    :name="activeConversation?.name ?? null"
                    :phone="selectedPhone"
                />

                <ChatSalePanel
                    :sale="activeSale"
                    :loading="saleLoading"
                    :confirming="confirmingPayment"
                    :error="saleError"
                    @confirm-payment="onConfirmPayment"
                    @refresh="loadActiveSale"
                />

                <div
                    ref="messagesContainer"
                    class="flex-1 space-y-3 overflow-y-auto bg-[url('data:image/svg+xml,%3Csvg width=%2260%22 height=%2260%22 viewBox=%220 0 60 60%22 xmlns=%22http://www.w3.org/2000/svg%22%3E%3Cg fill=%22none%22 fill-rule=%22evenodd%22%3E%3Cg fill=%22%23d4cdc4%22 fill-opacity=%220.15%22%3E%3Cpath d=%22M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z%22/%3E%3C/g%3E%3C/g%3E%3C/svg%3E')] p-4 dark:bg-none"
                >
                    <div v-if="loading && filteredMessages.length === 0" class="flex justify-center py-8">
                        <Loader2 class="h-5 w-5 animate-spin text-muted-foreground" />
                    </div>

                    <ChatMessageBubble
                        v-for="message in filteredMessages"
                        :key="`${message.id}-${message.message_id}`"
                        :message="message"
                        :resending="resendingId === message.id"
                        @resend="resendMessage"
                    />

                    <p
                        v-if="!loading && selectedPhone && filteredMessages.length === 0"
                        class="py-8 text-center text-sm text-muted-foreground"
                    >
                        No hay mensajes en esta conversación.
                    </p>
                </div>

                <ChatComposer
                    v-if="selectedPhone"
                    v-model="newMessage"
                    :sending="sending"
                    @submit="sendMessage"
                />

                <p v-if="sendError" class="bg-[#f0f2f5] px-4 pb-3 text-sm text-destructive dark:bg-muted/30">
                    {{ sendError }}
                </p>
            </section>
        </div>
    </AppLayout>
</template>
