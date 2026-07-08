<?php
require __DIR__ . '/../core/vendor/autoload.php';
$app = require_once __DIR__ . '/../core/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$columns = DB::select("DESCRIBE product_variants");
foreach ($columns as $col) {
    echo $col->Field . " - " . $col->Type . "\n";
}

echo "\n--- Attribute Value Columns ---\n";
$columns2 = DB::select("DESCRIBE attribute_values");
foreach ($columns2 as $col) {
    echo $col->Field . " - " . $col->Type . "\n";
}

echo "\n--- Attributes Columns ---\n";
$columns3 = DB::select("DESCRIBE attributes");
foreach ($columns3 as $col) {
    echo $col->Field . " - " . $col->Type . "\n";
}
