<?php

namespace App\Infrastructure\Whatsapp;

class MetaWhatsAppSettings
{
    public static function isConfigured(): bool
    {
        return self::accessToken() !== '' && self::phoneNumberId() !== '';
    }

    public static function accessToken(): string
    {
        return trim((string) config('services.whatsapp.access_token'));
    }

    public static function phoneNumberId(): string
    {
        return trim((string) config('services.whatsapp.phone_number_id'));
    }

    public static function verifyToken(): string
    {
        return trim((string) config('services.whatsapp.verify_token'));
    }

    public static function graphVersion(): string
    {
        return trim((string) config('services.whatsapp.graph_version', 'v21.0')) ?: 'v21.0';
    }

    public static function graphBaseUrl(): string
    {
        return 'https://graph.facebook.com/'.self::graphVersion();
    }
}
