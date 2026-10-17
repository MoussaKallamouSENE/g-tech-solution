<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'category' => [
                'sometimes',
                'nullable',
                'string',
                Rule::exists('categories', 'slug')->where('is_active', true),
            ],
            'brand' => ['sometimes', 'nullable', 'string', 'max:100'],
            'state' => ['sometimes', 'nullable', Rule::in(['neuf', 'occasion', 'reconditionné'])],
            'min_price' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:4294967295'],
            'max_price' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:4294967295'],
            'in_stock' => ['sometimes', 'boolean'],
            'sort' => ['sometimes', 'nullable', Rule::in(['price_asc', 'price_desc', 'name'])],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        if (isset($filters['min_price'], $filters['max_price']) && $filters['min_price'] > $filters['max_price']) {
            throw ValidationException::withMessages([
                'max_price' => ['Le prix maximum doit être supérieur ou égal au prix minimum.'],
            ]);
        }

        $query = Product::with('category')->where('is_active', true);

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%");
            });
        }

        if ($category = $filters['category'] ?? null) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $category));
        }

        if ($brand = $filters['brand'] ?? null) {
            $query->where('brand', $brand);
        }

        if ($state = $filters['state'] ?? null) {
            $query->where('state', $state);
        }

        if (isset($filters['min_price'])) {
            $query->where('price', '>=', (int) $filters['min_price']);
        }

        if (isset($filters['max_price'])) {
            $query->where('price', '<=', (int) $filters['max_price']);
        }

        if ($request->boolean('in_stock')) {
            $query->where('stock', '>', 0);
        }

        match ($filters['sort'] ?? null) {
            'price_asc' => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            'name' => $query->orderBy('name'),
            default => $query->latest()->orderByDesc('id'),
        };
        if (($filters['sort'] ?? null) !== null) {
            $query->orderBy('id');
        }

        return $query->paginate(12)->appends($filters);
    }

    public function show(int $id)
    {
        return Product::with('category')
            ->where('is_active', true)
            ->findOrFail($id);
    }
}
