<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\Delivery;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function (): void {
    Delivery::query()
        ->whereNotNull('location_expires_at')
        ->where('location_expires_at', '<=', now())
        ->update([
            'latitude' => null,
            'longitude' => null,
            'location_accuracy' => null,
            'location_updated_at' => null,
            'location_expires_at' => null,
        ]);
})->dailyAt('02:00')->name('delivery-location-retention')->withoutOverlapping();
