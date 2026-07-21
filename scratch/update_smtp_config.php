<?php

require __DIR__ . '/../core/vendor/autoload.php';
$app = require_once __DIR__ . '/../core/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$general = \App\Models\GeneralSetting::first();
if ($general) {
    $general->mail_config = [
        'name' => 'smtp',
        'host' => 'mail.vayromart.com',
        'port' => '465',
        'enc' => 'ssl',
        'username' => 'support@vayromart.com',
        'password' => 'SAJOL@SAJOL',
        'driver' => 'smtp'
    ];
    $general->email_from = 'support@vayromart.com';
    $general->email_from_name = 'Vayromart';
    $general->en = 1; // Enable email notification
    $general->save();
    echo "SMTP Configuration updated successfully in GeneralSettings!\n";
} else {
    echo "GeneralSetting model not found!\n";
}
