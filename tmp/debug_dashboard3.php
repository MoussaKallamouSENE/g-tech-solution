<?php
require '/var/www/html/vendor/autoload.php';
$app = require_once '/var/www/html/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();
use App\Models\User;
use App\Http\Controllers\Api\AdminDashboardController;
use Laravel\Sanctum\PersonalAccessToken;
$user = User::firstWhere('role', 'admin');
if (!$user) {
    $user = User::create(['name' => 'Admin Debug', 'email' => 'debug-admin-' . uniqid() . '@example.com', 'password' => bcrypt('tmp12345'), 'role' => 'admin']);
    $created = true;
} else {
    $created = false;
}
$controller = new AdminDashboardController();
try {
    $response = $controller->index();
    echo 'HTTP 200 OK' . "\n";
    $data = $response->getContent();
    $arr = json_decode($data, true);
    if ($arr !== null) {
        echo 'Keys: ' . implode(', ', array_keys($arr)) . "\n";
        echo 'Has error key: ' . (array_key_exists('error', $arr) ? 'yes' : 'no') . "\n";
        if (array_key_exists('message', $arr)) echo 'Message: ' . $arr['message'] . "\n";
    }
} catch (Throwable $e) {
    echo 'EXCEPTION: ' . get_class($e) . ': ' . $e->getMessage() . "\n";
    echo 'FILE: ' . $e->getFile() . ' LINE: ' . $e->getLine() . "\n";
}
if ($created) { $user->delete(); PersonalAccessToken::where('tokenable_id', $user->id)->delete(); }
echo "DONE\n";
