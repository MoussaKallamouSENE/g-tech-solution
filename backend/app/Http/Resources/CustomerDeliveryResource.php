<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerDeliveryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isActive = $this->status === 'in_delivery';
        $sharingActive = $isActive && $this->location_sharing_active;
        $hasPosition = $this->latitude !== null && $this->longitude !== null;
        $isExpired = $this->location_expires_at && $this->location_expires_at->isPast();
        $isStale = $sharingActive && (
            ($this->location_updated_at
                && $this->location_updated_at->lt(now()->subMinutes(config('services.delivery.location_stale_minutes', 5))))
            || $isExpired
        );
        $locationAvailable = $sharingActive && $hasPosition && ! $isExpired;

        return [
            'id' => $this->id,
            'status' => $this->status,
            'assigned_at' => $this->assigned_at,
            'picked_up_at' => $this->picked_up_at,
            'driver' => [
                'name' => $this->driver->name,
            ],
            'location_sharing_active' => $sharingActive,
            'location_available' => $locationAvailable,
            'location_is_stale' => (bool) $isStale,
            'location_stale_after_seconds' => config('services.delivery.location_stale_minutes', 5) * 60,
            'latitude' => $locationAvailable ? $this->latitude : null,
            'longitude' => $locationAvailable ? $this->longitude : null,
            'location_accuracy' => $locationAvailable ? $this->location_accuracy : null,
            'location_updated_at' => $sharingActive ? $this->location_updated_at : null,
        ];
    }
}
