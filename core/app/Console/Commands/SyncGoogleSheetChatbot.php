<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncGoogleSheetChatbot extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'chatbot:sync-sheet';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync the chatbot products catalog with the configured Google Sheet Web App URL';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info("Fetching chatbot settings...");
        $general = gs();
        $settings = [];
        if ($general->chatbot_settings) {
            $settings = is_string($general->chatbot_settings) 
                ? json_decode($general->chatbot_settings, true) 
                : (array)$general->chatbot_settings;
        }

        $enabled = $settings['google_sheet_sync_enabled'] ?? 0;
        $url = $settings['google_sheet_url'] ?? '';

        if (!$enabled) {
            $this->warn("Google Sheet sync is disabled in settings.");
            return 0;
        }

        if (empty($url)) {
            $this->error("Google Sheet URL is not configured.");
            return 1;
        }

        $this->info("Starting synchronization...");
        try {
            $syncService = new \App\Services\GoogleSheetSyncService();
            $result = $syncService->sync($url);
            
            $msg = "Successfully synchronized {$result['count']} products from Google Sheet!";
            $this->info($msg);
            return 0;
        } catch (\Exception $e) {
            $errorMsg = "Sync command failed: " . $e->getMessage();
            $this->error($errorMsg);
            Log::error($errorMsg);
            return 1;
        }
    }
}
