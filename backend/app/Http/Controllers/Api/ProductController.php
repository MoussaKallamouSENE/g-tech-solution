<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;

class ProductController extends Controller
{
    public function index()
    {
        return Product::with('category')
            ->where('is_active', true)
            ->latest()
            ->paginate(12);
    }

    public function show(int $id)
    {
        return Product::with('category')
            ->where('is_active', true)
            ->findOrFail($id);
    }
}