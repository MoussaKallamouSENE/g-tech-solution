<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Delivery extends Model
{
    public const STATUSES = ['to_prepare', 'ready_for_delivery', 'in_delivery', 'delivered', 'failed', 'cancelled'];

    protected $fillable = [
        'order_id', 'driver_id', 'status', 'assigned_at', 'picked_up_at',
        'delivered_at', 'failure_reason', 'latitude', 'longitude',
        'location_accuracy', 'location_updated_at', 'location_sharing_active',
        'location_sharing_started_at', 'location_sharing_stopped_at', 'location_expires_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'picked_up_at' => 'datetime',
        'delivered_at' => 'datetime',
        'latitude' => 'float',
        'longitude' => 'float',
        'location_accuracy' => 'integer',
        'location_updated_at' => 'datetime',
        'location_sharing_active' => 'boolean',
        'location_sharing_started_at' => 'datetime',
        'location_sharing_stopped_at' => 'datetime',
        'location_expires_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(DeliveryStatusHistory::class)->latest();
    }
}
