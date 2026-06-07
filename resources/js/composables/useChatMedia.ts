import type { ChatMessage } from '@/types/chat';

const MEDIA_ONLY_LABELS = ['📷 Imagen', '🎤 Audio', '🎬 Video', '🙂 Sticker', '📄 Documento'] as const;

export function mediaKind(message: ChatMessage): string {
    const meta = message.metadata;
    const explicit = meta?.type ?? meta?.whatsapp_message_type;

    if (explicit === 'sticker') {
        return 'sticker';
    }

    if (explicit && explicit !== 'text') {
        return String(explicit);
    }

    const raw = meta?.whatsapp_raw as { type?: string } | undefined;
    if (raw?.type === 'voice' || raw?.type === 'audio') {
        return 'audio';
    }
    if (raw?.type === 'sticker') {
        return 'sticker';
    }
    if (raw?.type === 'video') {
        return 'video';
    }
    if (raw?.type === 'image') {
        return 'image';
    }

    const mime = String(meta?.mime_type ?? '');
    if (mime.startsWith('audio/')) {
        return 'audio';
    }
    if (mime.startsWith('video/')) {
        return 'video';
    }
    if (mime.startsWith('image/')) {
        return message.content.trim() === '🙂 Sticker' ? 'sticker' : 'image';
    }

    const label = message.content.trim();
    if (label === '🎤 Audio') {
        return 'audio';
    }
    if (label === '🎬 Video') {
        return 'video';
    }
    if (label === '🙂 Sticker') {
        return 'sticker';
    }
    if (label === '📷 Imagen') {
        return 'image';
    }

    if (meta?.image_url || meta?.media_url) {
        return message.content.trim() === '🙂 Sticker' ? 'sticker' : 'image';
    }

    return 'text';
}

export function mediaSource(message: ChatMessage): string | null {
    const meta = message.metadata;
    if (!meta) {
        return null;
    }

    const local = meta.local_url as string | undefined;
    if (local) {
        return local;
    }

    const remote = (meta.media_url ?? meta.image_url) as string | undefined;
    if (!remote) {
        return null;
    }

    const storagePath = storagePathFromUrl(remote);
    if (storagePath) {
        return storagePath;
    }

    return route('media.proxy', { url: remote });
}

function storagePathFromUrl(url: string): string | null {
    try {
        const path = new URL(url).pathname;
        if (path.startsWith('/storage/')) {
            return path;
        }
    } catch {
        if (url.startsWith('/storage/')) {
            return url;
        }
    }

    return null;
}

export function mediaUnavailable(message: ChatMessage): boolean {
    return ['image', 'audio', 'video', 'sticker', 'document'].includes(mediaKind(message)) && !mediaSource(message);
}

export function isMediaOnlyLabel(content: string): boolean {
    return MEDIA_ONLY_LABELS.includes(content.trim() as (typeof MEDIA_ONLY_LABELS)[number]);
}

export function isStickerMessage(message: ChatMessage): boolean {
    return mediaKind(message) === 'sticker';
}
