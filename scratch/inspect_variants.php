<?php
$cachePath = __DIR__ . '/../core/storage/app/mohasagor_products_cache.json';
if (file_exists($cachePath)) {
    $data = json_decode(file_get_contents($cachePath), true);
    if (!empty($data)) {
        $count = 0;
        foreach ($data as $item) {
            if (isset($item['product_variants']) && !empty($item['product_variants'])) {
                echo "Product: " . $item['name'] . "\n";
                echo "Variants count: " . count($item['product_variants']) . "\n";
                echo json_encode($item['product_variants'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
                echo "---------------------------------\n";
                $count++;
                if ($count >= 5) break;
            }
        }
    }
}
