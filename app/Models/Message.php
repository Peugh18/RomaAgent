<?php

namespace App\Models;

use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory;

    protected $fillable = [
        'message_id',
        'phone_number',
        'customer_name',
        'content',
        'direction',
        'status',
        'is_pinned',
        'whatsapp_timestamp',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'is_pinned' => 'boolean',
            'whatsapp_timestamp' => 'datetime',
        ];
    }
}
