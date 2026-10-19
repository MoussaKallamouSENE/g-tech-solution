<?php
require '/var/www/html/vendor/autoload.php';
$app = require_once '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\User;
$user = User::where('email', 'senemoussa522@gmail.com')->first();
if (!$user) {
    echo "USER_NOT_FOUND\n";
    echo "Tous les emails:\n";
    foreach (User::all() as $u) {
        echo " - " . $u->email . " (role=" . $u->role . ")\n";
    }
} else {
    echo "USER_FOUND\n";
    echo "ID: " . $user->id . "\n";
    echo "Name: " . $user->name . "\n";
    echo "Email: " . $user->email . "\n";
    echo "Role: " . $user->role . "\n";
    echo "Password hash (first 30 chars): " . substr($user->password, 0, 30) . "...\n";
    // Le mot de passe est lu dans l'environnement (ADMIN_PASSWORD) et n'est
    // jamais écrit en clair dans le dépôt.
    $ok = password_verify((string) env('ADMIN_PASSWORD', ''), $user->password);
    echo "Password match: " . ($ok ? 'YES' : 'NO') . "\n";
}
