<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'reference',
        'brand',
        'description',
        'price',
        'stock',
        'stock_alert_threshold',
        'processor',
        'ram',
        'storage',
        'graphics_card',
        'screen',
        'state',
        'image',
        'is_active',
    ];

    protected $casts = [
        'price' => 'integer',
        'stock' => 'integer',
        'stock_alert_threshold' => 'integer',
        'is_active' => 'boolean',
    ];

    protected $appends = ['image_url'];

    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn () => $this->image ? asset($this->image) : null);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}