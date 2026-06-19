<?php
define('LARAVEL_START', microtime(true));
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ChatbotLandingPage;

$page = ChatbotLandingPage::orderBy('id', 'desc')->first();
if ($page) {
    echo "=== LANDING PAGE FOR PRODUCT: {$page->product->name} ===\n";
    // Print all occurrences of 'href' or 'action' containing 'product'
    preg_match_all('/(href|action)="([^"]+)"/i', $page->content, $matches);
    foreach ($matches[2] as $url) {
        if (strpos($url, 'product') !== false) {
            echo "URL matching 'product': {$url}\n";
        }
    }
}
