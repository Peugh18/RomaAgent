<?php

namespace App\Support;

use App\Models\CompanySetting;
use App\Models\HorarioConfig;

/**
 * Normaliza tallas en stock (mayúsculas consistentes).
 */
class NormalizadorStockTallas
{
    public const DEFAULT_SIZE_KEY = 'UNICA';

    public static function defaultSizeKey(): string
    {
        return once(function (): string {
            $companySettingId = CompanySetting::query()->value('id');
            $fromDb = $companySettingId
                ? HorarioConfig::query()->where('company_setting_id', $companySettingId)->value('standard_size')
                : null;

            if ($fromDb === null) {
                $fromDb = CompanySetting::query()->value('standard_size');
            }

            $key = strtoupper(trim((string) ($fromDb ?: self::DEFAULT_SIZE_KEY)));

            return $key !== '' ? $key : self::DEFAULT_SIZE_KEY;
        });
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

    public static function esTallaEstandar(?string $talla): bool
    {
        $key = strtoupper(trim((string) ($talla ?? self::defaultSizeKey())));

        return $key === self::defaultSizeKey();
    }

    /**
     * Etiqueta amigable para la clienta y para el agente (sin códigos internos tipo UNICA).
     */
    public static function etiquetaPublica(?string $talla = null): string
    {
        if (self::esTallaEstandar($talla)) {
            return 'talla estándar';
        }

        $key = strtoupper(trim((string) $talla));

        return $key !== '' ? sprintf('talla %s', $key) : 'talla estándar';
    }

    /**
     * Regla de tallas para catálogo y prompts del agente (multi-empresa).
     *
     * En BD la talla por defecto puede ser UNICA u otro código interno ({@see defaultSizeKey()}),
     * pero al cliente SIEMPRE se le dice "talla estándar". Nunca "única", "UNICA" ni el código interno.
     */
    public static function instruccionTallaParaPrompt(): string
    {
        $claveInterna = self::defaultSizeKey();

        return implode("\n", [
            'TALLAS (regla del sistema para toda la tienda):',
            '- La talla principal del catálogo es **talla estándar** (así se le dice a la clienta).',
            '- En base de datos el código interno puede ser "'.$claveInterna.'"; eso NO se menciona al cliente.',
            '- **Nunca** digas "única", "UNICA", "talla única" ni expongas códigos internos de talla.',
            '- Si la clienta no especifica talla, asume **talla estándar** al verificar stock y actualizar pedido.',
            '- Otras tallas (S, M, L, etc.) solo si existen en el stock del producto; también di "talla S", "talla M", nunca códigos raros.',
        ]);
    }

    /** Línea breve para el bloque de catálogo en el prompt. */
    public static function instruccionTallaParaCatalogo(): string
    {
        return 'Toda la tienda usa talla estándar. Al hablar con la clienta di "talla estándar", '
            .'nunca "única", "UNICA" ni el código interno "'.self::defaultSizeKey().'".';
    }
}
