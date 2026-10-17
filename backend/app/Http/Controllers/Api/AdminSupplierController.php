<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;

class AdminSupplierController extends Controller
{
    public function index(Request $request)
    {
        $query = Supplier::query()->withCount('purchaseReceipts');
        $search = trim((string) $request->query('search', ''));

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('contact_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        return $query->orderBy('name')->paginate(25)->withQueryString();
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());
        $supplier = Supplier::create($data);

        return response()->json($supplier, 201);
    }

    public function update(Request $request, int $id)
    {
        $data = $request->validate($this->rules());
        $supplier = Supplier::findOrFail($id);
        $supplier->update($data);

        return $supplier->fresh()->loadCount('purchaseReceipts');
    }

    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
