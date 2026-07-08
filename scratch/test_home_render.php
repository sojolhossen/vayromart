<?php
require __DIR__ . '/../core/vendor/autoload.php';
$app = require_once __DIR__ . '/../core/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request;

try {
    $request = Request::create('/', 'GET');
    // Set AJAX header
    $request->headers->set('X-Requested-With', 'XMLHttpRequest');
    $response = Route::dispatch($request);
    echo "AJAX status: " . $response->getStatusCode() . "\n";
    echo "AJAX Body snippet: " . substr($response->getContent(), 0, 500) . "\n\n";
} catch (\Exception $e) {
    echo "AJAX Error: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n\n";
}

try {
    $requestNormal = Request::create('/', 'GET');
    $responseNormal = Route::dispatch($requestNormal);
    echo "Normal status: " . $responseNormal->getStatusCode() . "\n";
    echo "Normal Body length: " . strlen($responseNormal->getContent()) . "\n";
} catch (\Exception $e) {
    echo "Normal Error: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n\n";
}
