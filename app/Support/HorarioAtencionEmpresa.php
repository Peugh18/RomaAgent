<?php

namespace App\Support;

use Carbon\Carbon;

class HorarioAtencionEmpresa
{
    /**
     * Evalúa si estamos en horario de atención (America/Lima).
     * Si no hay horario configurado, asume 9:00–22:00.
     */
    public static function estaEnHorario(?string $horarioAtencion): bool
    {
        $now = Carbon::now('America/Lima');
        $hour = (int) $now->format('G');

        if ($horarioAtencion === null || trim($horarioAtencion) === '') {
            return $hour >= 9 && $hour < 22;
        }

        if (preg_match('/(\d{1,2})\s*(?:am|AM|a\.?\s*m\.?).*?(\d{1,2})\s*(?:pm|PM|p\.?\s*m\.?)/u', $horarioAtencion, $matches)) {
            $inicio = (int) $matches[1];
            $fin = (int) $matches[2];
            if ($fin <= 12 && str_contains(mb_strtolower($horarioAtencion), 'pm')) {
                $fin = $fin === 12 ? 12 : $fin + 12;
            }
            if ($inicio <= 12 && preg_match('/\b9\s*am|\b10\s*am|\b11\s*am|\b8\s*am/u', mb_strtolower($horarioAtencion)) && $inicio < 12) {
                // keep morning hours as-is for common "9am - 10pm" patterns
            }

            return $hour >= $inicio && $hour < $fin;
        }

        return $hour >= 9 && $hour < 22;
    }
}
