<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Le seeder est idempotent : les comptes existants ne sont jamais
     * réécrits (le mot de passe d'un administrateur existant est conservé).
     */
    public function run(): void
    {
        $this->createAdmin();

        $this->call([
            DeliveryZoneSeeder::class,
            CatalogueSeeder::class,
        ]);
    }

    /**
     * Crée l'administrateur décrit par les variables d'environnement
     * ADMIN_EMAIL, ADMIN_NAME, ADMIN_PHONE et ADMIN_PASSWORD.
     *
     * Aucun mot de passe n'est écrit en clair dans le dépôt : sans
     * ADMIN_PASSWORD, un mot de passe aléatoire est généré puis affiché
     * une seule fois dans la console.
     */
    private function createAdmin(): void
    {
        $email = trim((string) env('ADMIN_EMAIL', 'admin@gtech.local'));
        $name = trim((string) env('ADMIN_NAME', 'Administrateur G-Tech'));
        $phone = trim((string) env('ADMIN_PHONE', '+221 77 000 00 00'));
        $password = (string) env('ADMIN_PASSWORD', '');

        if ($email === '') {
            $this->command?->warn('ADMIN_EMAIL non défini : aucun administrateur créé.');

            return;
        }

        if (User::where('email', $email)->exists()) {
            $this->command?->warn("Administrateur déjà présent : {$email} (mot de passe conservé).");

            return;
        }

        $generated = $password === '';
        $password = $generated ? Str::password(16) : $password;

        User::create([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'password' => Hash::make($password),
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $this->command?->info("Administrateur créé : {$email}");

        if ($generated) {
            $this->command?->info("Mot de passe généré (à changer dès la première connexion) : {$password}");
        }
    }
}
