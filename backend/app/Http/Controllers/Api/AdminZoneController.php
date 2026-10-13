<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeliveryZone;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminZoneController extends Controller
{
    private function rules(?int $ignoreId = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('delivery_zones', 'name')->ignore($ignoreId)],
            'fee' => ['required', 'integer', 'min:0'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    private function messages(): array
    {
        return [
            'name.required' => 'Le nom de la zone est obligatoire.',
            'name.unique' => 'Une zone porte déjà ce nom.',
            'fee.required' => 'Le tarif de livraison est obligatoire.',
            'fee.integer' => 'Le tarif doit être un nombre entier (en FCFA).',
            'fee.min' => 'Le tarif ne peut pas être négatif.',
        ];
    }

    public function index()
    {
        return DeliveryZone::orderBy('name')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules(), $this->messages());

        return response()->json(DeliveryZone::create($data), 201);
    }

    public function update(Request $request, int $id)
    {
        $zone = DeliveryZone::findOrFail($id);
        $zone->update($request->validate($this->rules($zone->id), $this->messages()));

        return $zone;
    }
}