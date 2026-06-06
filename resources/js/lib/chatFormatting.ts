export function chatStatusLabel(status: string): string {
    const map: Record<string, string> = {
        pending: 'Enviando…',
        sent: 'Enviado',
        delivered: 'Entregado',
        read: 'Leído',
        failed: 'Falló',
    };

    return map[status] ?? status;
}

export function formatChatTime(value: string | null): string {
    if (!value) {
        return '';
    }

    return new Date(value).toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit' });
}
