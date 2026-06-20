<script setup lang="ts">
import ChatComposer from '@/components/chat/ChatComposer.vue';
import ChatConversationList from '@/components/chat/ChatConversationList.vue';
import ChatHumanAlertBanner from '@/components/chat/ChatHumanAlertBanner.vue';
import ChatMessageBubble from '@/components/chat/ChatMessageBubble.vue';
import ChatSalePanel from '@/components/chat/ChatSalePanel.vue';
import ChatThreadHeader from '@/components/chat/ChatThreadHeader.vue';
import SaleTransitionModal from '@/components/sales/SaleTransitionModal.vue';
import CrmAnimatedSection from '@/components/crm/CrmAnimatedSection.vue';
import CrmPageHero from '@/components/crm/CrmPageHero.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useChat } from '@/composables/useChat';
import { useHumanAttentionAlert } from '@/composables/useHumanAttentionAlert';
import { useActiveSale } from '@/composables/useSale';
import { type BreadcrumbItem } from '@/types';
import type { Sale, SaleTransition } from '@/types/sale';
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Bot, Loader2, MessageSquare } from 'lucide-vue-next';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Chat WhatsApp', href: '/chat' }];

const chatMode = ref<'bot' | 'human'>('bot');
const transitionOpen = ref(false);
const activeTransition = ref<SaleTransition | null>(null);

const phoneFromUrl = typeof window !== 'undefined'
    ? new URLSearchParams(window.location.search).get('phone')
    : null;

const {
    selectedPhone,
    newMessage,
    conversations,
    filteredMessages,
    loading,
    sending,
    resendingId,
    sendError,
    loadError,
    messagesContainer,
    selectConversation,
    sendMessage,
    resendMessage,
} = useChat({ initialPhone: phoneFromUrl });

const phoneRef = computed(() => selectedPhone.value);

const {
    sale: activeSale,
    loading: saleLoading,
    transitioning,
    error: saleError,
    loadActiveSale,
} = useActiveSale(phoneRef);

const activeConversation = computed(() =>
    conversations.value.find((conversation) => conversation.phone === selectedPhone.value),
);

const { humanAttentionChats, showAlert, dismissAlert } = useHumanAttentionAlert(conversations);

const goToHumanChat = async (phone: string) => {
    await selectConversation(phone);
    dismissAlert();
};

const onTransitionCompleted = async (sale: Sale) => {
    if (activeSale.value?.id === sale.id) {
        activeSale.value = sale;
    }

    await loadActiveSale();
};

const updateLabels = (labels: any[]) => {
    if (activeConversation.value) {
        activeConversation.value.labels = labels;
    }
};

const openTransition = (transition: SaleTransition) => {
    activeTransition.value = transition;
    transitionOpen.value = true;
};
</script>

<template>
    <Head title="Chat WhatsApp" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <ChatHumanAlertBanner
            :visible="showAlert"
            :chats="humanAttentionChats"
            @dismiss="dismissAlert"
            @select="goToHumanChat"
        />

        <div class="crm-page !gap-4 pb-4 pt-2">
            <CrmPageHero
                compact
                title="Chat WhatsApp"
                description="Conversaciones en tiempo real. Cambia a modo humano para responder manualmente."
                :icon="MessageSquare"
                variant="emerald"
                :stats="[{ label: 'Conversaciones', value: conversations.length }]"
            />

            <CrmAnimatedSection :delay="60">
            <div
                class="flex h-[calc(100vh-11rem)] min-h-[32rem] flex-col gap-4 overflow-hidden lg:flex-row lg:gap-0 lg:rounded-2xl lg:border lg:border-border/50 lg:shadow-md"
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
                    @mode-change="chatMode = $event"
                    @labels-updated="updateLabels"
                />

                <ChatSalePanel
                    :sale="activeSale"
                    :loading="saleLoading"
                    :transitioning="transitioning"
                    :error="saleError"
                    @open-transition="openTransition"
                    @refresh="loadActiveSale"
                />

                <div
                    ref="messagesContainer"
                    class="flex-1 space-y-3 overflow-y-auto bg-[url('data:image/svg+xml,%3Csvg width=%2260%22 height=%2260%22 viewBox=%220 0 60 60%22 xmlns=%22http://www.w3.org/2000/svg%22%3E%3Cg fill=%22none%22 fill-rule=%22evenodd%22%3E%3Cg fill=%22%23d4cdc4%22 fill-opacity=%220.15%22%3E%3Cpath d=%22M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z%22/%3E%3C/g%3E%3C/g%3E%3C/svg%3E')] p-4 dark:bg-none"
                >
                    <div v-if="loading && filteredMessages.length === 0" class="flex justify-center py-8">
                        <Loader2 class="h-5 w-5 animate-spin text-muted-foreground" />
                    </div>

                    <p
                        v-if="loadError"
                        class="py-4 text-center text-sm text-destructive"
                    >
                        {{ loadError }}
                    </p>

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
                    v-if="selectedPhone && chatMode === 'human'"
                    v-model="newMessage"
                    :sending="sending"
                    @submit="sendMessage"
                />

                <div
                    v-else-if="selectedPhone"
                    class="flex items-center gap-2 border-t border-border bg-[#f0f2f5] px-4 py-3 text-sm text-muted-foreground dark:bg-muted/30"
                >
                    <Bot class="h-4 w-4 shrink-0 text-emerald-600" />
                    La IA responde sola. Cambia a <strong class="mx-1 font-medium text-foreground">Humano</strong> para escribir manualmente.
                </div>

                <p v-if="sendError" class="bg-[#f0f2f5] px-4 pb-3 text-sm text-destructive dark:bg-muted/30">
                    {{ sendError }}
                </p>
            </section>

            <SaleTransitionModal
                v-model:open="transitionOpen"
                :sale="activeSale"
                :transition="activeTransition"
                @completed="onTransitionCompleted"
            />
        </div>
            </CrmAnimatedSection>
        </div>
    </AppLayout>
</template>
