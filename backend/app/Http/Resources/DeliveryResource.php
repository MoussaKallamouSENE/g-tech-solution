<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $hasStoredLocation = $this->latitude !== null && $this->longitude !== null;
        $locationIsStale = $this->location_sharing_active
            && $hasStoredLocation
            && ($this->location_updated_at === null
                || $this->location_updated_at->lt(now()->subMinutes(config('services.delivery.location_stale_minutes', 5)))
                || ($this->location_expires_at && $this->location_expires_at->isPast()));
        $locationIsAvailable = $this->location_sharing_active
            && $hasStoredLocation
            && (! $this->location_expires_at || $this->location_expires_at->isFuture());

        return [
            'id' => $this->id,
            'status' => $this->status,
            'assigned_at' => $this->assigned_at,
            'picked_up_at' => $this->picked_up_at,
            'delivered_at' => $this->delivered_at,
            'failure_reason' => $this->failure_reason,
            'driver' => $this->whenLoaded('driver', fn () => [
                'id' => $this->driver->id,
                'name' => $this->driver->name,
            ]),
            'order' => $this->whenLoaded('order', fn () => [
                'id' => $this->order->id,
                'number' => $this->order->number,
                'status' => $this->order->status,
                'customer_name' => $this->order->customer_name,
                'customer_phone' => $this->order->customer_phone,
                'customer_address' => $this->order->customer_address,
            ]),
            'location_sharing_active' => $this->location_sharing_active,
            'location_available' => $locationIsAvailable,
            'location_is_stale' => $locationIsStale,
            'latitude' => $locationIsAvailable ? $this->latitude : null,
            'longitude' => $locationIsAvailable ? $this->longitude : null,
            'location_accuracy' => $locationIsAvailable ? $this->location_accuracy : null,
            'location_updated_at' => $locationIsAvailable ? $this->location_updated_at : null,
            'status_history' => $this->whenLoaded('statusHistory', fn () => $this->statusHistory->map(fn ($entry) => [
                'id' => $entry->id,
                'status' => $entry->status,
                'note' => $entry->note,
                'created_at' => $entry->created_at,
                'changed_by' => $entry->changedBy?->name,
            ])),
        ];
    }
}
