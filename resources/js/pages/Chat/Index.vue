<script setup lang="ts">
import ChatComposer from '@/components/chat/ChatComposer.vue';
import ChatConversationList from '@/components/chat/ChatConversationList.vue';
import ChatHumanAlertBanner from '@/components/chat/ChatHumanAlertBanner.vue';
import ChatMessageBubble from '@/components/chat/ChatMessageBubble.vue';
import ChatSalePanel from '@/components/chat/ChatSalePanel.vue';
import ChatThreadHeader from '@/components/chat/ChatThreadHeader.vue';
import ChatCustomerHistoryDrawer from '@/components/chat/ChatCustomerHistoryDrawer.vue';
import SaleTransitionModal from '@/components/sales/SaleTransitionModal.vue';
import CrmAnimatedSection from '@/components/crm/CrmAnimatedSection.vue';
import CrmPageHero from '@/components/crm/CrmPageHero.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useChat } from '@/composables/useChat';
import { useHumanAttentionAlert } from '@/composables/useHumanAttentionAlert';
import { useActiveSale } from '@/composables/useSale';
import { type BreadcrumbItem } from '@/types';
import type { Sale, SaleTransition } from '@/types/sale';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Bot, Loader2, MessageSquare, PanelRightOpen } from 'lucide-vue-next';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Chat WhatsApp', href: '/chat' }];

const chatMode = ref<'bot' | 'human'>('bot');
const transitionOpen = ref(false);
const activeTransition = ref<SaleTransition | null>(null);
const mobileSalePanelOpen = ref(false);

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
    selectedImage,
    imagePreviewUrl,
    setImage,
    clearImage,
    selectConversation,
    sendMessage,
    resendMessage,
} = useChat({ initialPhone: phoneFromUrl });

const deselectConversation = () => {
    // Navigate to /chat without phone parameter or just clear it
    window.history.pushState({}, '', '/chat');
    window.dispatchEvent(new Event('popstate'));
    // Since useChat doesn't have an explicit clear, we can reload or handle it.
    // Actually, selectConversation(null) might not be typed to allow null, let's see.
    // If not, we can just force a reload, but SPA is better. Let's try selectConversation('').
    void selectConversation('');
};

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

const customerHistoryOpen = ref(false);
const customerData = ref<any>(null);

const openCustomerHistory = async () => {
    customerHistoryOpen.value = true;
    if (!selectedPhone.value) return;
    try {
        const encoded = encodeURIComponent(selectedPhone.value);
        customerData.value = await apiJson(`/api/customers/${encoded}`);
    } catch (e) {
        console.error('Error fetching customer history', e);
    }
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

        <div class="flex h-[calc(100vh-80px)] flex-col overflow-hidden pb-0 pt-0">
            <CrmAnimatedSection :delay="60" class="flex flex-1 flex-col overflow-hidden">
                <div
                    class="flex flex-1 flex-col overflow-hidden lg:flex-row lg:gap-0 lg:border-t lg:border-border"
                >
                    <!-- Left Sidebar: Conversations -->
                    <ChatConversationList
                        :conversations="conversations"
                        :selected-phone="selectedPhone"
                        :loading="loading"
                        @select="selectConversation"
                        class="w-full shrink-0 border-r border-border lg:w-80"
                        :class="selectedPhone ? 'hidden lg:flex' : 'flex'"
                    />

                    <!-- Middle Column: Chat Thread -->
                    <section 
                        class="flex min-w-0 flex-1 flex-col overflow-hidden bg-[#efeae2] dark:bg-[#0b141a]"
                        :class="!selectedPhone ? 'hidden lg:flex' : 'flex'"
                    >
                        <ChatThreadHeader
                            :name="activeConversation?.name ?? null"
                            :phone="selectedPhone"
                            @mode-change="chatMode = $event"
                            @labels-updated="updateLabels"
                            @open-sale-panel="mobileSalePanelOpen = true"
                            @open-history-panel="openCustomerHistory"
                            @back="deselectConversation"
                        />

                        <div class="relative flex-1 overflow-hidden">
                            <!-- Background Pattern -->
                            <div 
                                class="pointer-events-none absolute inset-0 z-0 bg-[url('/storage/logos/2NIgcUYuHK75VhjbnATXeUsApwnYv1CQylp2XAtf.png')] bg-repeat opacity-40 dark:opacity-[0.04]"
                                style="background-size: 350px;"
                            ></div>
                            
                            <!-- Messages -->
                            <div ref="messagesContainer" class="relative z-10 h-full space-y-3 overflow-y-auto p-4">
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
            </div>

                <ChatComposer
                    v-if="selectedPhone && chatMode === 'human'"
                    v-model="newMessage"
                    :sending="sending"
                    :image-preview-url="imagePreviewUrl"
                    @submit="sendMessage"
                    @image-selected="setImage"
                    @clear-image="clearImage"
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

                <!-- Right Sidebar: Sale Panel (Desktop) -->
                <aside
                    v-if="activeSale || saleLoading"
                    class="hidden w-80 shrink-0 flex-col overflow-y-auto border-l border-border bg-card lg:flex"
                >
                    <ChatSalePanel
                        :sale="activeSale"
                        :loading="saleLoading"
                        :transitioning="transitioning"
                        :error="saleError"
                        @open-transition="openTransition"
                        @refresh="loadActiveSale"
                    />
                </aside>

                <!-- Mobile Sale Panel Drawer -->
                <Sheet v-model:open="mobileSalePanelOpen">
                    <SheetContent side="right" class="w-full max-w-md p-0 sm:max-w-md">
                        <SheetHeader class="border-b border-border bg-muted/30 px-4 py-3 text-left">
                            <SheetTitle class="text-sm">Datos de la Venta</SheetTitle>
                        </SheetHeader>
                        <div class="h-full overflow-y-auto pb-10">
                            <ChatSalePanel
                                :sale="activeSale"
                                :loading="saleLoading"
                                :transitioning="transitioning"
                                :error="saleError"
                                @open-transition="openTransition"
                                @refresh="loadActiveSale"
                            />
                        </div>
                    </SheetContent>
                </Sheet>

            <SaleTransitionModal
                v-model:open="transitionOpen"
                :sale="activeSale"
                :transition="activeTransition"
                @completed="onTransitionCompleted"
            />
            
            <ChatCustomerHistoryDrawer 
                v-model:open="customerHistoryOpen"
                :customer="customerData"
            />
        </div>
            </CrmAnimatedSection>
        </div>
    </AppLayout>
</template>
