<?php
$cachePath = __DIR__ . '/../core/storage/app/mohasagor_products_cache.json';
if (file_exists($cachePath)) {
    $data = json_decode(file_get_contents($cachePath), true);
    if (!empty($data)) {
        foreach ($data as $item) {
            if (isset($item['product_variants']) && !empty($item['product_variants'])) {
                echo "Found item: " . $item['name'] . "\n";
                echo "product_variants: " . json_encode($item['product_variants'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
                break;
            }
        }
    } else {
        echo "Cache empty\n";
    }
} else {
    echo "No cache file found\n";
}
