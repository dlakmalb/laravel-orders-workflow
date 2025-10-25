<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationLog extends Model
{
    protected $fillable = [
        'order_id',
        'customer_id',
        'channel',
        'status',
        'total_cents',
        'payload',
        'success',
        'error',
        'sent_at'
    ];

    protected $casts = [
        'payload' => 'array',
        'success' => 'boolean',
        'sent_at' => 'immutable_datetime',
    ];
}
