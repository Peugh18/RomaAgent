<?php

namespace App\Support;

class RomaApiHeaders
{
    /**
     * @return array<string, string>
     */
    public static function withAuth(array $extra = []): array
    {
        $headers = [
            'Accept' => 'application/json',
            'ngrok-skip-browser-warning' => 'true',
            ...$extra,
        ];

        $token = (string) config('services.roma.token');
        if ($token !== '') {
            $headers['Authorization'] = 'Bearer '.$token;
            $headers['X-Roma-Sync-Token'] = $token;
        }

        return $headers;
    }

    /**
     * @return array<string, string>
     */
    public static function forJsonPost(): array
    {
        return self::withAuth([
            'Content-Type' => 'application/json',
        ]);
    }
}
