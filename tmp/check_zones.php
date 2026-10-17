<?php
require '/var/www/html/vendor/autoload.php';
$app = require_once '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\DeliveryZone;
echo 'Delivery zones count: ' . DeliveryZone::count() . "\n";
foreach (DeliveryZone::all() as $z) {
    echo ' - ' . $z->name . ' (id=' . $z->id . ', fee=' . $z->fee . ', is_active=' . ($z->is_active ? 'true' : 'false') . ")\n";
}
