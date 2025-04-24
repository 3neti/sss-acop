<?php

namespace App\Commerce\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    /** @use HasFactory<\Database\Factories\OrderFactory> */
    use HasFactory;

    protected $fillable = [
        'reference_id',
        'meta',
        'amount',
        'currency',
        'callback_url',
    ];

    protected $casts = [
        'meta' => 'array',
        'amount' => 'float',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $order) {
            if (empty($order->currency)) {
                $order->currency = 'PHP';
            }
        });
    }
}
