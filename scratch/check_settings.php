<?php
use App\Models\GeneralSetting;

require __DIR__ . '/../core/vendor/autoload.php';
$app = require_once __DIR__ . '/../core/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$general = gs();
echo "Chatbot Settings:\n";
print_r($general->chatbot_settings);
