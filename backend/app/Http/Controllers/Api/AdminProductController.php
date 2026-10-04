<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminProductController extends Controller
{
    private function rules(?int $ignoreId = null): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'reference' => ['required', 'string', 'max:100', Rule::unique('products', 'reference')->ignore($ignoreId)],
            'brand' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'price' => ['required', 'integer', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'stock_alert_threshold' => ['required', 'integer', 'min:0'],
            'processor' => ['nullable', 'string', 'max:100'],
            'ram' => ['nullable', 'string', 'max:100'],
            'storage' => ['nullable', 'string', 'max:100'],
            'graphics_card' => ['nullable', 'string', 'max:100'],
            'screen' => ['nullable', 'string', 'max:100'],
            'state' => ['required', Rule::in(['neuf', 'occasion', 'reconditionné'])],
            'is_active' => ['required', 'boolean'],
        ];
    }

    private function messages(): array
    {
        return [
            'category_id.required' => 'Choisissez une catégorie.',
            'name.required' => 'Le nom est obligatoire.',
            'reference.required' => 'La référence est obligatoire.',
            'reference.unique' => 'Cette référence existe déjà.',
            'price.required' => 'Le prix est obligatoire.',
            'price.integer' => 'Le prix doit être un nombre entier (en FCFA).',
            'price.min' => 'Le prix ne peut pas être négatif.',
            'stock.integer' => 'Le stock doit être un nombre entier.',
            'stock.min' => 'Le stock ne peut pas être négatif.',
            'state.in' => "L'état doit être neuf, occasion ou reconditionné.",
        ];
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'produit';
        $slug = $base;
        $i = 2;

        while (Product::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    public function index(Request $request)
    {
        $query = Product::with('category');

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%");
            });
        }

        if ($request->boolean('low_stock')) {
            $query->whereColumn('stock', '<=', 'stock_alert_threshold');
        }

        return $query->orderBy('name')->paginate(20)->withQueryString();
    }

    public function show(int $id)
    {
        return Product::with('category')->findOrFail($id);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules(), $this->messages());
        $data['slug'] = $this->uniqueSlug($data['name']);

        return response()->json(Product::create($data)->load('category'), 201);
    }

    public function update(Request $request, int $id)
    {
        $product = Product::findOrFail($id);
        $product->update($request->validate($this->rules($product->id), $this->messages()));

        return $product->load('category');
    }

    public function uploadImage(Request $request, int $id)
    {
        $product = Product::findOrFail($id);

        $request->validate([
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'image.required' => 'Choisissez une image.',
            'image.mimes' => "L'image doit être au format JPG, PNG ou WebP.",
            'image.max' => "L'image est trop lourde (2 Mo maximum).",
            'image.uploaded' => "L'envoi a échoué : l'image dépasse probablement 2 Mo.",
        ]);

        $file = $request->file('image');
        $directory = public_path('uploads/products');
        File::ensureDirectoryExists($directory);

        $name = Str::uuid()->toString().'.'.$file->guessExtension();
        $file->move($directory, $name);

        $this->removeImageFile($product);
        $product->update(['image' => 'uploads/products/'.$name]);

        return $product->fresh()->load('category');
    }

    public function deleteImage(int $id)
    {
        $product = Product::findOrFail($id);
        $this->removeImageFile($product);
        $product->update(['image' => null]);

        return $product->fresh()->load('category');
    }

    private function removeImageFile(Product $product): void
    {
        if ($product->image && str_starts_with($product->image, 'uploads/products/')) {
            File::delete(public_path($product->image));
        }
    }

    public function patch(Request $request, int $id)
    {
        $product = Product::findOrFail($id);
        $product->update($request->validate([
            'stock' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ], $this->messages()));

        return $product->load('category');
    }
}