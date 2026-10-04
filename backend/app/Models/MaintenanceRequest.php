<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaintenanceRequest extends Model
{
    protected $fillable = [
        'number', 'user_id', 'name', 'phone', 'email', 'device_type',
        'brand', 'problem', 'status', 'admin_notes',
    ];
}