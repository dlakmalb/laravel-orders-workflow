<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    protected $fillable = [
        'order_id',
        'amount_cents',
        'reason',
        'status',
        'idempotency_key',
        'processed_at',
    ];

    protected $casts = [
        'processed_at' => 'immutable_datetime',
    ];

    public const STATUS_REQUESTED = 'REQUESTED';

    public const STATUS_PROCESSED = 'PROCESSED';

    public const STATUS_FAILED = 'FAILED';

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
