<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleSheetSyncService
{
    /**
     * Fetch products from Google Sheet Web App URL and compile to chatbot context JSON.
     *
     * @param string $url
     * @return array
     */
    public function sync($url)
    {
        if (empty($url)) {
            throw new \Exception("Google Sheet Web App URL is empty.");
        }

        Log::info("Starting Google Sheet chatbot sync from URL: {$url}");

        // Fetch data from Google Apps Script Web App URL
        $response = Http::timeout(20)->get($url);

        if (!$response->successful()) {
            throw new \Exception("Failed to fetch data from Google Sheet. Status code: " . $response->status());
        }

        $sheetData = $response->json();

        if (!is_array($sheetData)) {
            throw new \Exception("Invalid JSON response received from Google Sheet.");
        }

        $formattedProducts = [];
        $index = 1;

        foreach ($sheetData as $row) {
            // Normalize keys to lowercase and trim spaces
            $normalizedRow = [];
            foreach ($row as $key => $value) {
                $cleanKey = strtolower(trim($key));
                $normalizedRow[$cleanKey] = $value;
            }

            // Skip empty rows (name is required)
            $name = $normalizedRow['name'] ?? $normalizedRow['product name'] ?? $normalizedRow['product'] ?? '';
            if (empty($name)) {
                continue;
            }

            $id = $normalizedRow['id'] ?? $normalizedRow['product id'] ?? $index++;
            $priceVal = $normalizedRow['price'] ?? $normalizedRow['sale price'] ?? $normalizedRow['regular price'] ?? '0';
            
            // Clean pricing format to ensure it says "BDT"
            $price = str_ireplace(['tk', 'taka', 'bdt', '৳', ' '], '', $priceVal);
            $price = trim($price) . ' BDT';

            $stockVal = $normalizedRow['stock'] ?? $normalizedRow['stock quantity'] ?? $normalizedRow['quantity'] ?? '10';
            $stock = is_numeric($stockVal) && intval($stockVal) > 0 
                ? intval($stockVal) . " items in stock" 
                : (stripos($stockVal, 'out') !== false ? "Out of stock" : "10 items in stock");

            $summary = $normalizedRow['summary'] ?? $normalizedRow['short description'] ?? '';
            $description = $normalizedRow['description'] ?? $normalizedRow['details'] ?? $normalizedRow['specification'] ?? '';
            $link = $normalizedRow['link'] ?? $normalizedRow['url'] ?? $normalizedRow['product link'] ?? '';

            $formattedProducts[] = [
                'id' => $id,
                'name' => trim($name),
                'price' => $price,
                'stock' => $stock,
                'summary' => trim(strip_tags(html_entity_decode($summary))),
                'description' => trim(strip_tags(html_entity_decode($description))),
                'link' => trim($link)
            ];
        }

        // Read existing chatbot data.json if it exists to preserve category, brands, coupons, shipping, etc.
        $jsonFilePath = storage_path('app/chatbot/data.json');
        $existingData = [];
        if (file_exists($jsonFilePath)) {
            try {
                $existingData = json_decode(file_get_contents($jsonFilePath), true) ?: [];
            } catch (\Exception $e) {
                Log::warning("Could not read existing chatbot data.json during Google Sheet sync: " . $e->getMessage());
            }
        }

        // Overwrite only products
        $existingData['products'] = $formattedProducts;

        // Ensure directories exist
        $storageDir = storage_path('app/chatbot');
        if (!file_exists($storageDir)) {
            mkdir($storageDir, 0775, true);
        }

        // Write complete JSON structure back
        $jsonContent = json_encode($existingData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents($jsonFilePath, trim($jsonContent));

        Log::info("Google Sheet chatbot sync completed. Formatted products: " . count($formattedProducts));

        return [
            'success' => true,
            'count' => count($formattedProducts),
            'file_path' => 'storage/app/chatbot/data.json'
        ];
    }
}
