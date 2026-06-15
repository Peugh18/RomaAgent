<?php

namespace App\Models;

use Database\Factories\AgenteConfigFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

/**
 * Configuración del Agente IA
 * Antes: campos dispersos en CompanySetting
 */
class AgenteConfig extends Model
{
    /** @use HasFactory<AgenteConfigFactory> */
    use HasFactory;

    protected $fillable = [
        'company_setting_id',
        'activado',
        'modelo',
        'api_key_encrypted',
        'temperatura',
        'tono_bot',
        'estilo_comunicacion',
        'personalidad_bot',
        'estilo_ventas',
        'respuesta_si_es_bot',
    ];

    protected function casts(): array
    {
        return [
            'activado' => 'boolean',
            'temperatura' => 'decimal:2',
        ];
    }

    protected $hidden = [
        'api_key_encrypted',
    ];

    public function companySetting(): BelongsTo
    {
        return $this->belongsTo(CompanySetting::class);
    }

    /**
     * Obtiene la API key desencriptada
     */
    public function obtenerApiKey(): ?string
    {
        if (empty($this->api_key_encrypted)) {
            return null;
        }

        $candidates = [];

        try {
            $candidates[] = Crypt::decryptString($this->api_key_encrypted);
        } catch (\Exception) {
        }

        try {
            $candidates[] = decrypt($this->api_key_encrypted);
        } catch (\Exception) {
        }

        foreach ($candidates as $candidate) {
            $normalizada = self::normalizarApiKeyPlana($candidate);

            if ($normalizada !== null && $normalizada !== '') {
                return $normalizada;
            }
        }

        return null;
    }

    /**
     * Guarda la API key con el formato de cifrado actual.
     */
    public function guardarApiKey(string $apiKey): void
    {
        $this->update([
            'api_key_encrypted' => Crypt::encryptString($apiKey),
        ]);
    }

    /**
     * Normaliza valores legacy cifrados con encrypt() (serializados).
     */
    public static function normalizarApiKeyPlana(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        if (str_starts_with($value, 's:') && preg_match('/^s:\d+:"(.*)";$/s', $value, $matches)) {
            return stripcslashes($matches[1]);
        }

        return $value;
    }

    /**
     * Activa el agente IA
     */
    public function activar(): void
    {
        $this->update(['activado' => true]);
    }

    /**
     * Desactiva el agente IA
     */
    public function desactivar(): void
    {
        $this->update(['activado' => false]);
    }
}
