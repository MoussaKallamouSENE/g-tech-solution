<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminMaintenanceController extends Controller
{
    public const STATUSES = ['new', 'in_progress', 'ready', 'done', 'cancelled'];

    public function index(Request $request)
    {
        $query = MaintenanceRequest::query();

        $status = $request->query('status');
        if (in_array($status, self::STATUSES, true)) {
            $query->where('status', $status);
        }

        $search = trim((string) $request->query('search', ''));
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
        $item = MaintenanceRequest::findOrFail($id);

        $data = $request->validate([
            'status' => ['required', Rule::in(self::STATUSES)],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'status.required' => 'Le statut est obligatoire.',
            'status.in' => 'Statut invalide.',
        ]);

        $item->update($data);

        return $item->fresh();
    }
}