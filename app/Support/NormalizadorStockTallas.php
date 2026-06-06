<?php

namespace App\Support;

/**
 * Normaliza tallas en stock (mayúsculas consistentes).
 */
class NormalizadorStockTallas
{
    public const DEFAULT_SIZE_KEY = 'UNICA';

    public static function defaultSizeKey(): string
    {
        $fromDb = \App\Models\CompanySetting::query()->value('standard_size');
        $key = strtoupper(trim((string) ($fromDb ?: self::DEFAULT_SIZE_KEY)));

        return $key !== '' ? $key : self::DEFAULT_SIZE_KEY;
    }

    /**
     * @param  array<string|int, int|string|null>  $stockBySize
     * @return array<string, int>
     */
    public static function normalize(array $stockBySize): array
    {
        $out = [];
        foreach ($stockBySize as $size => $qty) {
            $key = strtoupper(trim((string) $size));
            if ($key === '') {
                continue;
            }
            $out[$key] = max(0, (int) $qty);
        }

        return $out;
    }

    /** @return array<string, int> */
    public static function standardSizes(int $defaultStock = 0): array
    {
        return [self::defaultSizeKey() => $defaultStock];
    }
}
