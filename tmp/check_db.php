<?php
require '/var/www/html/vendor/autoload.php';
$app = require_once '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\Product;
use App\Models\User;
use App\Models\Order;
use App\Models\Delivery;
echo 'Products: ' . Product::count() . "\n";
echo 'Users: ' . User::count() . "\n";
echo 'Admins: ' . User::where('role','admin')->count() . "\n";
echo 'Orders: ' . Order::count() . "\n";
echo 'Deliveries: ' . Delivery::count() . "\n";
echo "Admin emails:\n";
foreach (User::where('role','admin')->get() as $u) {
    echo ' - ' . $u->email . ' (id=' . $u->id . ')' . "\n";
}
echo "Sample products:\n";
foreach (Product::take(5)->get() as $p) {
    echo ' - ' . $p->name . ' (stock=' . $p->stock . ')' . "\n";
}
