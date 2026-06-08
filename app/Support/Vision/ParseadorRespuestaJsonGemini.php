<?php

namespace App\Support\Vision;

class ParseadorRespuestaJsonGemini
{
    /**
     * @return array<string, mixed>|null
     */
    public static function parse(?string $text): ?array
    {
        if ($text === null || trim($text) === '') {
            return null;
        }

        $text = trim($text);
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text) ?? $text;
        $text = preg_replace('/\s*```\s*$/', '', $text) ?? $text;

        $decoded = json_decode($text, true);

        return is_array($decoded) ? $decoded : null;
    }
}
