<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ZonaEnvio extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'zonas_envio';

    protected $fillable = [
        'departamento',
        'provincia',
        'distrito',
        'tipo_envio',
        'costo_referencial',
        'activo',
        'observaciones',
        'datos_requeridos',
    ];

    protected function casts(): array
    {
        return [
            'costo_referencial' => 'decimal:2',
            'activo' => 'boolean',
            'datos_requeridos' => 'array',
        ];
    }
}
