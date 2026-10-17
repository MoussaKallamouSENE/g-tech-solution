<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminMaintenanceController extends Controller
{
    public const STATUSES = ['new', 'in_progress', 'ready', 'done', 'cancelled'];

    private const TRANSITIONS = [
        'new' => ['in_progress', 'cancelled'],
        'in_progress' => ['ready', 'cancelled'],
        'ready' => ['done', 'cancelled'],
        'done' => [],
        'cancelled' => [],
    ];

    public function index(Request $request)
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ], [
            'status.in' => 'Statut de demande invalide.',
            'search.string' => 'La recherche doit être un texte.',
            'search.max' => 'La recherche ne peut pas dépasser 100 caractères.',
            'page.integer' => 'Le numéro de page doit être un entier.',
            'page.min' => 'Le numéro de page doit être supérieur à zéro.',
        ]);

        $query = MaintenanceRequest::query();

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $search = trim($filters['search'] ?? '');
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('number', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        return $query->latest()->orderByDesc('id')->paginate(20)->withQueryString();
    }

    public function update(Request $request, int $id)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(self::STATUSES)],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'status.required' => 'Le statut est obligatoire.',
            'status.in' => 'Statut invalide.',
        ]);

        $item = DB::transaction(function () use ($id, $data) {
            $item = MaintenanceRequest::query()->lockForUpdate()->findOrFail($id);

            if ($data['status'] !== $item->status && ! in_array($data['status'], self::TRANSITIONS[$item->status] ?? [], true)) {
                throw ValidationException::withMessages([
                    'status' => ["Transition impossible : une demande « {$item->status} » ne peut pas passer au statut « {$data['status']} »."],
                ]);
            }

            $item->update($data);

            return $item;
        });

        return $item->fresh();
    }
}
