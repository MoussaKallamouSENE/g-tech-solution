<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8'],
        ], [
            'name.required' => 'Le nom est obligatoire.',
            'email.required' => "L'adresse e-mail est obligatoire.",
            'email.email' => "L'adresse e-mail n'est pas valide.",
            'email.unique' => 'Un compte existe déjà avec cette adresse e-mail.',
            'password.required' => 'Le mot de passe est obligatoire.',
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
        ]);

        $data['email'] = strtolower($data['email']);
        $user = User::create($data);

        return response()->json([
            'user' => $user->fresh(),
            'token' => $user->createToken('gtech')->plainTextToken,
        ], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => "L'adresse e-mail est obligatoire.",
            'email.email' => "L'adresse e-mail n'est pas valide.",
            'password.required' => 'Le mot de passe est obligatoire.',
        ]);

        $user = User::where('email', strtolower($data['email']))->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['E-mail ou mot de passe incorrect.'],
            ]);
        }

        return response()->json([
            'user' => $user,
            'token' => $user->createToken('gtech')->plainTextToken,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    public function me(Request $request)
    {
        return $request->user();
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'phone' => ['nullable', 'string', 'max:30'],
        ], [
            'name.required' => 'Le nom est obligatoire.',
            'email.required' => "L'adresse e-mail est obligatoire.",
            'email.email' => "L'adresse e-mail n'est pas valide.",
            'email.unique' => 'Un compte existe déjà avec cette adresse e-mail.',
        ]);

        $data['email'] = strtolower($data['email']);

        $user->update($data);

        return response()->json(['user' => $user->fresh()]);
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'different:current_password'],
            'password_confirmation' => ['required', 'same:password'],
        ], [
            'current_password.required' => 'Le mot de passe actuel est obligatoire.',
            'password.required' => 'Le nouveau mot de passe est obligatoire.',
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
            'password.different' => 'Le nouveau mot de passe doit différer de l’ancien.',
            'password_confirmation.required' => 'La confirmation du mot de passe est obligatoire.',
            'password_confirmation.same' => 'La confirmation ne correspond pas au nouveau mot de passe.',
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Le mot de passe actuel est incorrect.',
            ]);
        }

        $user->update(['password' => Hash::make($data['password'])]);

        return response()->json(['message' => 'Mot de passe mis à jour avec succès.']);
    }

    public function sendPasswordResetLink(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        $email = strtolower($data['email']);
        $user = User::where('email', $email)->first();

        // Réponse identique que l'utilisateur existe ou non (évite l'énumération d'emails)
        if (! $user) {
            return response()->json([
                'message' => 'Si un compte existe avec cet e-mail, un lien de réinitialisation a été envoyé.',
            ]);
        }

        $token = Str::random(60);

        $user->update([
            'password_reset_token' => $token,
            'password_reset_token_expires_at' => now()->addMinutes(60),
        ]);

        // Le lien est écrit dans les journaux (MAIL_MAILER=log en développement).
        // En production, configurer un vrai SMTP pour l'envoyer par e-mail.
        Log::info('Lien de réinitialisation de mot de passe', [
            'email' => $email,
            'token' => $token,
            'url' => config('app.frontend_url').'/reset-password?token='.$token.'&email='.urlencode($email),
        ]);

        return response()->json([
            'message' => 'Si un compte existe avec cet e-mail, un lien de réinitialisation a été envoyé.',
        ]);
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'password.confirmed' => 'La confirmation du mot de passe ne correspond pas.',
        ]);

        $email = strtolower($data['email']);

        $user = User::where('email', $email)
            ->where('password_reset_token', $data['token'])
            ->where('password_reset_token_expires_at', '>', now())
            ->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'token' => 'Le token de réinitialisation est invalide ou a expiré.',
            ]);
        }

        $user->update([
            'password' => Hash::make($data['password']),
            'password_reset_token' => null,
            'password_reset_token_expires_at' => null,
        ]);

        return response()->json([
            'message' => 'Mot de passe réinitialisé avec succès. Vous pouvez maintenant vous connecter.',
        ]);
    }
}
