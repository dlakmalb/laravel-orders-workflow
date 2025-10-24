<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = ['external_order_id', 'customer_id', 'status', 'currency', 'total_cents', 'placed_at'];

    protected $casts = [
        'placed_at' => 'immutable_datetime',
    ];
}
