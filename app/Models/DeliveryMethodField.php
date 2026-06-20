<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryMethodField extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_required' => 'boolean',
    ];

    public function deliveryMethod()
    {
        return $this->belongsTo(DeliveryMethod::class);
    }
}
