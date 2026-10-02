<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeliveryZone;

class DeliveryZoneController extends Controller
{
    public function index()
    {
        return DeliveryZone::where('is_active', true)
            ->orderBy('fee')
            ->orderBy('name')
            ->get(['id', 'name', 'fee']);
    }
}