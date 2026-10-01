<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with('category')->where('is_active', true);

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%");
            });
        }

        if ($category = $request->query('category')) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $category));
        }

        if ($brand = $request->query('brand')) {
            $query->where('brand', $brand);
        }

        if ($state = $request->query('state')) {
            $query->where('state', $state);
        }

        if (is_numeric($request->query('min_price'))) {
            $query->where('price', '>=', (int) $request->query('min_price'));
        }

        if (is_numeric($request->query('max_price'))) {
            $query->where('price', '<=', (int) $request->query('max_price'));
        }

        if ($request->boolean('in_stock')) {
            $query->where('stock', '>', 0);
        }

        match ($request->query('sort')) {
            'price_asc' => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            'name' => $query->orderBy('name'),
            default => $query->latest()->orderByDesc('id'),
        };

        return $query->paginate(12)->withQueryString();
    }

    public function show(int $id)
    {
        return Product::with('category')
            ->where('is_active', true)
            ->findOrFail($id);
    }
}