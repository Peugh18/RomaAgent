<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    /** @use HasFactory<\Database\Factories\MessageFactory> */
    use HasFactory;
    protected $fillable = [
        'message_id',
        'phone_number',
        'customer_name',
        'content',
        'direction',
        'status',
        'whatsapp_timestamp',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'whatsapp_timestamp' => 'datetime',
        ];
    }
}
