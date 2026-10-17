<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Order extends Model
{
    protected $fillable = [
        'number', 'invoice_number', 'user_id', 'delivery_zone_id', 'customer_name', 'customer_phone',
        'whatsapp_consent', 'customer_address', 'notes', 'subtotal', 'delivery_fee', 'total',
        'payment_method', 'status', 'email_status', 'email_sent_at',
        'email_attempts', 'email_error', 'whatsapp_status', 'whatsapp_sent_at',
        'whatsapp_attempts', 'whatsapp_error',
    ];

    protected $casts = [
        'subtotal' => 'integer',
        'delivery_fee' => 'integer',
        'total' => 'integer',
        'whatsapp_consent' => 'boolean',
        'email_attempts' => 'integer',
        'email_sent_at' => 'datetime',
        'whatsapp_attempts' => 'integer',
        'whatsapp_sent_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Order $order): void {
            $createdAt = $order->created_at ?? now();
            $order->invoice_number ??= sprintf(
                'GTS-FA-%s-%s',
                $createdAt->format('Y'),
                Str::upper(Str::random(16)),
            );
        });

        static::updating(function (Order $order): void {
            if ($order->isDirty('invoice_number')) {
                $order->invoice_number = $order->getOriginal('invoice_number');
            }
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(DeliveryZone::class, 'delivery_zone_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function delivery(): HasOne
    {
        return $this->hasOne(Delivery::class);
    }
}
