<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryMethod extends Model
{
    protected $guarded = [];

    public function fields()
    {
        return $this->hasMany(DeliveryMethodField::class)->orderBy('sort_order');
    }
}
