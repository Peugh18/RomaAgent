<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends Model
{
    protected $fillable = [
        'product_id',
        'color',
        'image_path',
        'image_url',
        'sizes_stock',
        'color_profile',
        'color_profile_at',
        'image_embedding',
        'embedding_at',
    ];

    protected $casts = [
        'sizes_stock' => 'array',
        'color_profile' => 'array',
        'color_profile_at' => 'datetime',
        'image_embedding' => 'array',
        'embedding_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
