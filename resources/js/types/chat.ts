export interface ChatMessage {
    id: number;
    message_id: string;
    phone_number: string;
    customer_name: string | null;
    content: string;
    direction: 'incoming' | 'outgoing';
    status: 'pending' | 'sent' | 'delivered' | 'read' | 'failed';
    whatsapp_timestamp: string | null;
    metadata: Record<string, unknown> | null;
    created_at: string;
    updated_at: string;
}

export interface ChatConversation {
    phone: string;
    name: string | null;
    last_message: string;
    last_at: string | null;
    direction: ChatMessage['direction'];
    status: ChatMessage['status'];
    ia_paused?: boolean;
    ia_pause_reason?: string | null;
    pending_payment?: boolean;
    active_sale_status?: string | null;
}
