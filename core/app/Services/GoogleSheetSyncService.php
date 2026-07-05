<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleSheetSyncService
{
    /**
     * Fetch product details dynamically using Google Sheets API v4 with active OAuth credentials.
     *
     * @return array
     * @throws \Exception
     */
    public function sync()
    {
        $general = gs();
        $settings = [];
        if ($general->chatbot_settings) {
            $settings = is_string($general->chatbot_settings) 
                ? json_decode($general->chatbot_settings, true) 
                : (array)$general->chatbot_settings;
        }

        $clientId = $settings['google_client_id'] ?? '';
        $clientSecret = $settings['google_client_secret'] ?? '';
        $refreshToken = $settings['google_refresh_token'] ?? '';
        $spreadsheetId = $settings['google_spreadsheet_id'] ?? '';
        $sheetName = $settings['google_sheet_name'] ?? 'Sheet1';

        if (empty($clientId) || empty($clientSecret) || empty($refreshToken)) {
            throw new \Exception("Google Sheets integration is not authorized. Please connect your Google account in Settings first.");
        }

        if (empty($spreadsheetId)) {
            throw new \Exception("Google Spreadsheet ID is not set.");
        }

        // Auto-extract Spreadsheet ID if full URL is pasted
        if (preg_match('/\/d\/([a-zA-Z0-9-_]+)/', $spreadsheetId, $matches)) {
            $spreadsheetId = $matches[1];
        }

        // Get active Access Token (refresh if expired)
        $accessToken = $this->getAccessToken($clientId, $clientSecret, $refreshToken, $settings);

        Log::info("Fetching Google Sheet data for sheet: {$sheetName} (Spreadsheet ID: {$spreadsheetId})");

        // Fetch sheet values using Google Sheets API v4
        $url = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}/values/" . urlencode($sheetName) . "!A:Z";
        $response = Http::withToken($accessToken)->timeout(25)->get($url);

        if (!$response->successful()) {
            throw new \Exception("Failed to fetch Google Sheet data: " . ($response->json()['error']['message'] ?? $response->body()));
        }

        $body = $response->json();
        $values = $body['values'] ?? [];

        if (empty($values) || count($values) < 2) {
            throw new \Exception("Spreadsheet sheet '{$sheetName}' is empty or contains no product rows.");
        }

        // Parse headers and rows
        $headers = array_map(function($h) {
            return strtolower(trim($h));
        }, $values[0]);

        $formattedProducts = [];
        $index = 1;

        for ($i = 1; $i < count($values); $i++) {
            $row = $values[$i];
            if (empty($row)) continue;

            $rowData = [];
            foreach ($headers as $colIndex => $headerName) {
                $rowData[$headerName] = $row[$colIndex] ?? '';
            }

            // Skip empty rows (name is required)
            $name = $rowData['name'] ?? $rowData['product name'] ?? $rowData['product'] ?? '';
            if (empty($name)) {
                continue;
            }

            $id = $rowData['id'] ?? $rowData['product id'] ?? $index++;
            $priceVal = $rowData['price'] ?? $rowData['sale price'] ?? $rowData['regular price'] ?? '0';
            
            // Clean pricing format to ensure it says "BDT"
            $price = str_ireplace(['tk', 'taka', 'bdt', '৳', ' '], '', $priceVal);
            $price = trim($price) . ' BDT';

            $stockVal = $rowData['stock'] ?? $rowData['stock quantity'] ?? $rowData['quantity'] ?? '10';
            $stock = is_numeric($stockVal) && intval($stockVal) > 0 
                ? intval($stockVal) . " items in stock" 
                : (stripos($stockVal, 'out') !== false ? "Out of stock" : "10 items in stock");

            $summary = $rowData['summary'] ?? $rowData['short description'] ?? '';
            $description = $rowData['description'] ?? $rowData['details'] ?? $rowData['specification'] ?? '';
            $link = $rowData['link'] ?? $rowData['url'] ?? $rowData['product link'] ?? '';

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

        // Read existing chatbot data.json if it exists to preserve other table categories, coupons, etc.
        $jsonFilePath = storage_path('app/chatbot/data.json');
        $existingData = [];
        if (file_exists($jsonFilePath)) {
            try {
                $existingData = json_decode(file_get_contents($jsonFilePath), true) ?: [];
            } catch (\Exception $e) {
                Log::warning("Could not read existing chatbot data.json during Google Sheet sync: " . $e->getMessage());
            }
        }

        // Overwrite products
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

    /**
     * Retrieve active Access Token or refresh it if expired/expiring soon.
     */
    private function getAccessToken($clientId, $clientSecret, $refreshToken, &$settings)
    {
        $accessToken = $settings['google_access_token'] ?? '';
        $expiresAt = $settings['google_token_expires_at'] ?? 0;

        // Refresh token if expired or close to expiring (within 60 seconds)
        if (empty($accessToken) || time() >= ($expiresAt - 60)) {
            Log::info("Refreshing Google OAuth access token...");
            $response = Http::post('https://oauth2.googleapis.com/token', [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'refresh_token' => $refreshToken,
                'grant_type' => 'refresh_token',
            ]);

            if (!$response->successful()) {
                throw new \Exception("Failed to refresh Google access token: " . ($response->json()['error_description'] ?? $response->body()));
            }

            $tokenData = $response->json();
            $accessToken = $tokenData['access_token'];
            
            // Save refreshed details
            $settings['google_access_token'] = $accessToken;
            if (isset($tokenData['refresh_token'])) {
                $settings['google_refresh_token'] = $tokenData['refresh_token'];
            }
            $settings['google_token_expires_at'] = time() + ($tokenData['expires_in'] ?? 3600);

            $general = gs();
            $general->chatbot_settings = $settings;
            $general->save();
        }

        return $accessToken;
    }
}
