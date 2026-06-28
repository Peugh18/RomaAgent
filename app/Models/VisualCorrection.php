<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VisualCorrection extends Model
{
    protected $fillable = [
        'image_path',
        'image_hash',
        'huella_forma',
        'image_embedding',
        'product_id',
        'original_product_id',
    ];

    protected $casts = [
        'image_embedding' => 'array',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function originalProduct()
    {
        return $this->belongsTo(Product::class, 'original_product_id');
    }
}
