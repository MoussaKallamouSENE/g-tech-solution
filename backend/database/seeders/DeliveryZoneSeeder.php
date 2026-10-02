<?php

namespace Database\Seeders;

use App\Models\DeliveryZone;
use Illuminate\Database\Seeder;

class DeliveryZoneSeeder extends Seeder
{
    public function run(): void
    {
        $zones = [
            'Plateau, Médina, Fann' => 1500,
            'Mermoz, Sacré-Cœur, Liberté' => 2000,
            'Parcelles Assainies, Grand Yoff' => 2000,
            'Almadies, Ngor, Ouakam, Yoff' => 2500,
            'Pikine, Guédiawaye' => 2500,
            'Rufisque, Bargny' => 3500,
        ];

        foreach ($zones as $name => $fee) {
            DeliveryZone::updateOrCreate(['name' => $name], ['fee' => $fee, 'is_active' => true]);
        }
    }
}