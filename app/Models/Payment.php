<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'order_id',
        'provider',
        'provider_ref',
        'amount_cents',
        'status', // 'SUCCEEDED' | 'FAILED'
        'paid_at',
    ];

    protected $casts = [
        'paid_at' => 'immutable_datetime',
    ];

    // Optional: small status constants to avoid string literals everywhere
    public const STATUS_SUCCEEDED = 'SUCCEEDED';
    public const STATUS_FAILED = 'FAILED';

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
