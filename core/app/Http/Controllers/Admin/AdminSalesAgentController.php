<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class AdminSalesAgentController extends Controller
{
    /**
     * Display the Sales Agent Config Dashboard
     */
    public function index()
    {
        $pageTitle = 'Sales Agent & Google Sheet Config';
        
        $general = gs();
        $settings = [];
        if ($general->chatbot_settings) {
            $settings = is_string($general->chatbot_settings) 
                ? json_decode($general->chatbot_settings, true) 
                : (array)$general->chatbot_settings;
        }

        return view('admin.setting.sales_agent.index', compact('pageTitle', 'settings'));
    }

    /**
     * Update Facebook Webhook and Google Sheet Credentials
     */
    public function update(Request $request)
    {
        $request->validate([
            'facebook_verify_token' => 'nullable|string|max:100',
            'facebook_page_access_token' => 'nullable|string|max:500',
            'google_client_id' => 'nullable|string|max:255',
            'google_client_secret' => 'nullable|string|max:255',
            'google_spreadsheet_id' => 'nullable|string|max:255',
            'google_sheet_name' => 'nullable|string|max:100',
            'google_sheet_sync_enabled' => 'nullable|in:0,1',
        ]);

        $general = gs();
        
        $settings = [];
        if ($general->chatbot_settings) {
            $settings = is_string($general->chatbot_settings) 
                ? json_decode($general->chatbot_settings, true) 
                : (array)$general->chatbot_settings;
        }

        // Update Facebook keys
        $settings['facebook_verify_token'] = $request->facebook_verify_token;
        $settings['facebook_page_access_token'] = $request->facebook_page_access_token;
        
        // Update Google sheets keys
        $settings['google_client_id'] = $request->google_client_id;
        $settings['google_client_secret'] = $request->google_client_secret;
        $settings['google_spreadsheet_id'] = $request->google_spreadsheet_id;
        $settings['google_sheet_name'] = $request->google_sheet_name ?: 'Sheet1';
        $settings['google_sheet_sync_enabled'] = $request->google_sheet_sync_enabled ? 1 : 0;

        $general->chatbot_settings = $settings;
        $general->save();

        $notify[] = ['success', 'Sales Agent configurations updated successfully.'];
        return back()->withNotify($notify);
    }

    /**
     * Redirect to Google OAuth Consent screen
     */
    public function googleRedirect()
    {
        $general = gs();
        $settings = [];
        if ($general->chatbot_settings) {
            $settings = is_string($general->chatbot_settings) 
                ? json_decode($general->chatbot_settings, true) 
                : (array)$general->chatbot_settings;
        }

        $clientId = $settings['google_client_id'] ?? '';
        
        if (empty($clientId)) {
            $notify[] = ['error', 'Please configure your Google Client ID first.'];
            return back()->withNotify($notify);
        }

        $redirectUri = route('admin.setting.sales_agent.google.callback');

        $url = "https://accounts.google.com/o/oauth2/v2/auth?" . http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/spreadsheets.readonly',
            'access_type' => 'offline',
            'prompt' => 'consent'
        ]);

        return redirect()->away($url);
    }

    /**
     * Handle callback authorization redirect from Google
     */
    public function googleCallback(Request $request)
    {
        $code = $request->query('code');
        if (empty($code)) {
            $notify[] = ['error', 'Authorization code not found from Google.'];
            return redirect()->route('admin.setting.sales_agent.index')->withNotify($notify);
        }

        $general = gs();
        $settings = [];
        if ($general->chatbot_settings) {
            $settings = is_string($general->chatbot_settings) 
                ? json_decode($general->chatbot_settings, true) 
                : (array)$general->chatbot_settings;
        }

        $clientId = $settings['google_client_id'] ?? '';
        $clientSecret = $settings['google_client_secret'] ?? '';

        $redirectUri = route('admin.setting.sales_agent.google.callback');

        $response = Http::post('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
        ]);

        if (!$response->successful()) {
            $notify[] = ['error', 'Failed to retrieve access token from Google: ' . ($response->json()['error_description'] ?? $response->body())];
            return redirect()->route('admin.setting.sales_agent.index')->withNotify($notify);
        }

        $tokenData = $response->json();
        
        $settings['google_access_token'] = $tokenData['access_token'] ?? null;
        $settings['google_refresh_token'] = $tokenData['refresh_token'] ?? ($settings['google_refresh_token'] ?? null);
        $settings['google_token_expires_at'] = time() + ($tokenData['expires_in'] ?? 3600);

        $general->chatbot_settings = $settings;
        $general->save();

        $notify[] = ['success', 'Google Account connected successfully! You can now sync your Google Sheet.'];
        return redirect()->route('admin.setting.sales_agent.index')->withNotify($notify);
    }

    /**
     * Trigger Google Sheet Sync manually
     */
    public function syncGoogleSheet()
    {
        try {
            $syncService = new \App\Services\GoogleSheetSyncService();
            $result = $syncService->sync();

            return response()->json([
                'success' => true,
                'message' => "Successfully synced {$result['count']} products from Google Sheet!"
            ]);
        } catch (\Exception $e) {
            Log::error("Manual Google Sheet Sync Error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Sync failed: ' . $e->getMessage()
            ]);
        }
    }
}
