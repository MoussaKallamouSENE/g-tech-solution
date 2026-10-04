<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MaintenanceRequestController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'device_type' => ['required', Rule::in(['portable', 'bureau', 'imprimante', 'mobile', 'reseau', 'autre'])],
            'brand' => ['nullable', 'string', 'max:100'],
            'problem' => ['required', 'string', 'min:10', 'max:3000'],
        ], [
            'name.required' => 'Le nom est obligatoire.',
            'phone.required' => 'Le téléphone est obligatoire pour vous rappeler.',
            'email.email' => "L'adresse e-mail n'est pas valide.",
            'device_type.required' => "Choisissez le type d'appareil.",
            'device_type.in' => "Type d'appareil invalide.",
            'problem.required' => 'Décrivez le problème rencontré.',
            'problem.min' => 'Décrivez le problème en quelques mots de plus (10 caractères minimum).',
        ]);

        $item = MaintenanceRequest::create($data + ['user_id' => $request->user('sanctum')?->id]);
        $item->update(['number' => 'RM-'.str_pad((string) $item->id, 6, '0', STR_PAD_LEFT)]);

        return response()->json(['number' => $item->number], 201);
    }
}