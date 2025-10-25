<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    protected $fillable = [
        'external_order_id',
        'customer_id',
        'status',
        'currency',
        'total_cents',
        'placed_at'
    ];

    protected $casts = [
        'placed_at' => 'immutable_datetime',
    ];

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_PAID = 'PAID';
    public const STATUS_FAILED = 'FAILED';
    public const STATUS_CANCELLED = 'CANCELLED';

    public function isTerminal(): bool
    {
        return in_array($this->status, [self::STATUS_PAID, self::STATUS_FAILED, self::STATUS_CANCELLED], true);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
