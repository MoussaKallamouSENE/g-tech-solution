<?php
require '/var/www/html/vendor/autoload.php';
$app = require_once '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;
$user = User::create(['name' => 'Admin Test Tmp2', 'email' => 'admin-test-tmp2-' . uniqid() . '@example.com', 'password' => bcrypt('tmp12345'), 'role' => 'admin']);
$token = $user->createToken('tmp')->plainTextToken;
$request = Illuminate\Http\Request::create('/api/admin/dashboard', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $token]);
try {
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    $response = $kernel->handle($request);
    echo 'HTTP ' . $response->getStatusCode() . "\n";
    $content = $response->getContent();
    $data = json_decode($content, true);
    if ($data !== null) {
        echo 'JSON keys: ' . implode(', ', array_keys($data)) . "\n";
        if (array_key_exists('gps_active', $data)) echo 'gps_active: ' . $data['gps_active'] . "\n";
        if (array_key_exists('deliveries_in_delivery', $data)) echo 'deliveries_in_delivery: ' . $data['deliveries_in_delivery'] . "\n";
        if (array_key_exists('message', $data)) echo 'Message: ' . $data['message'] . "\n";
    } else {
        echo 'Raw: ' . substr($content, 0, 300) . "\n";
    }
} catch (Throwable $e) {
    echo 'EXCEPTION: ' . $e->getMessage() . "\n";
}
$user->delete();
PersonalAccessToken::where('tokenable_id', $user->id)->delete();
echo "USER_DELETED\n";
