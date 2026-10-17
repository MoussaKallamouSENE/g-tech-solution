<?php
require '/var/www/html/vendor/autoload.php';
$app = require_once '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\DeliveryZone;

$zones = [
    ['name' => 'Dakar Plateau', 'fee' => 1500, 'is_active' => true],
    ['name' => 'Dakar Medina', 'fee' => 1500, 'is_active' => true],
    ['name' => 'Dakar Fann', 'fee' => 1500, 'is_active' => true],
    ['name' => 'Dakar Mermoz', 'fee' => 2000, 'is_active' => true],
    ['name' => 'Dakar Sacre-Coeur', 'fee' => 2000, 'is_active' => true],
    ['name' => 'Dakar Ouakam', 'fee' => 2000, 'is_active' => true],
    ['name' => 'Dakar Almadies', 'fee' => 2500, 'is_active' => true],
    ['name' => 'Dakar Ngor', 'fee' => 2500, 'is_active' => true],
    ['name' => 'Dakar Yoff', 'fee' => 2500, 'is_active' => true],
    ['name' => 'Dakar Parcelles Assainies', 'fee' => 2500, 'is_active' => true],
    ['name' => 'Dakar Grand Yoff', 'fee' => 2500, 'is_active' => true],
    ['name' => 'Pikine', 'fee' => 3000, 'is_active' => true],
    ['name' => 'Guediawaye', 'fee' => 3000, 'is_active' => true],
    ['name' => 'Rufisque', 'fee' => 3500, 'is_active' => true],
    ['name' => 'Diamniadio', 'fee' => 4000, 'is_active' => true],
    ['name' => 'Bargny', 'fee' => 3500, 'is_active' => true],
    ['name' => 'Keur Massar', 'fee' => 3500, 'is_active' => true],
    ['name' => 'Thies', 'fee' => 5000, 'is_active' => true],
    ['name' => 'Mbour', 'fee' => 5000, 'is_active' => true],
    ['name' => 'Kaolack', 'fee' => 7000, 'is_active' => true],
    ['name' => 'Fatick', 'fee' => 8000, 'is_active' => true],
    ['name' => 'Ziguinchor', 'fee' => 10000, 'is_active' => true],
];

foreach ($zones as $z) {
    DeliveryZone::firstOrCreate(['name' => $z['name']], $z);
    echo "Zone: " . $z['name'] . " (fee=" . $z['fee'] . ")\n";
}
echo "DONE\n";
