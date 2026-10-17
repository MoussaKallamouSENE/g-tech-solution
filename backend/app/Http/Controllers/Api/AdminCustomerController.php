<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class AdminCustomerController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ], [
            'search.string' => 'La recherche doit être un texte.',
            'search.max' => 'La recherche ne peut pas dépasser 100 caractères.',
            'page.integer' => 'Le numéro de page doit être un entier.',
            'page.min' => 'Le numéro de page doit être supérieur à zéro.',
        ]);

        $query = User::where('role', 'customer')
            ->withCount('orders')
            ->withSum(['orders as spent' => fn ($q) => $q->where('status', '!=', 'cancelled')], 'total');

        $search = trim($filters['search'] ?? '');
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        return $query->latest()->orderByDesc('id')->paginate(20)->withQueryString();
    }
}
