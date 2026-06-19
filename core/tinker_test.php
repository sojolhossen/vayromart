<?php
define('LARAVEL_START', microtime(true));
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$conv = App\Models\ChatbotConversation::orderBy('updated_at', 'desc')->first();
if ($conv) {
    echo "=== CONVERSATION ID: {$conv->id} ===\n";
    $messages = App\Models\ChatbotMessage::where('conversation_id', $conv->id)->orderBy('created_at', 'desc')->take(10)->get();
    foreach ($messages->reverse() as $m) {
        echo "[{$m->created_at}] {$m->sender}: {$m->message}\n";
    }
}
