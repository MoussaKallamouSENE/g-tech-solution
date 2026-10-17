<?php
require '/var/www/html/vendor/autoload.php';
$app = require_once '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\User;
$user = User::where('email','admin@gtech.local')->first();
if ($user) {
    echo 'Admin found: ' . $user->email . ' (id=' . $user->id . ', role=' . $user->role . ')' . "\n";
    $pwdOk = password_verify('admin123', $user->password);
    echo 'Password correct: ' . ($pwdOk ? 'YES' : 'NO') . "\n";
} else {
    echo 'Admin NOT found' . "\n";
}
use App\Models\Product;
echo 'Products count: ' . Product::count() . "\n";
