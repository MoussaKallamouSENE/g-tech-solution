<?php
require '/var/www/html/vendor/autoload.php';
$app = require_once '/var/www/html/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::create('/api/admin/dashboard', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
try {
    $response = $kernel->handle($request);
    echo 'HTTP ' . $response->getStatusCode() . "\n";
    $content = $response->getContent();
    $data = json_decode($content, true);
    if ($data !== null) {
        echo 'JSON keys: ' . implode(', ', array_keys($data)) . "\n";
        echo 'Has message key: ' . (array_key_exists('message', $data) ? 'yes' : 'no') . "\n";
        if (array_key_exists('message', $data)) echo 'Message: ' . $data['message'] . "\n";
    } else {
        echo 'Raw: ' . substr($content, 0, 300) . "\n";
    }
} catch (Throwable $e) {
    echo 'EXCEPTION: ' . $e->getMessage() . "\n";
}
