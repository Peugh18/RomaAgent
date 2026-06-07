<?php

namespace App\Models;

use Database\Factories\EmpresaInfoConfigFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Información de la empresa
 * Antes: campos en CompanySetting
 */
class EmpresaInfoConfig extends Model
{
    /** @use HasFactory<EmpresaInfoConfigFactory> */
    use HasFactory;

    protected $fillable = [
        'company_setting_id',
        'company_name',
        'ruc',
        'razon_social',
        'celular',
        'email',
        'website',
        'logo_path',
        'actividad_economica',
        'informacion_adicional',
        'social_networks',
        'address',
    ];

    protected function casts(): array
    {
        return [
            'social_networks' => 'array',
        ];
    }

    public function companySetting(): BelongsTo
    {
        return $this->belongsTo(CompanySetting::class);
    }

    /**
     * URL pública del logo
     */
    public function logoUrl(): ?string
    {
        if (empty($this->logo_path)) {
            return null;
        }

        return Storage::url($this->logo_path);
    }

    /**
     * Datos de contacto formateados
     *
     * @return array<string, string|null>
     */
    public function datosContacto(): array
    {
        return [
            'whatsapp' => $this->celular,
            'email' => $this->email,
            'website' => $this->website,
            'direccion' => $this->address,
        ];
    }
}
