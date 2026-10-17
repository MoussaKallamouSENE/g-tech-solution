<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Compte administrateur par défaut.
        // Mot de passe temporaire : à changer dès la première connexion.
        User::create([
            'name' => 'Administrateur G-Tech',
            'email' => 'admin@gtech.local',
            'phone' => '+221 77 000 00 00',
            'password' => Hash::make('daf71b6980a29944d2f6af31'),
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $this->call([
            DeliveryZoneSeeder::class,
            CatalogueSeeder::class,
        ]);
    }
}
