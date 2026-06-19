<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminNotification extends Model
{
    public function user()
    {
    	return $this->belongsTo(User::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::created(function ($notification) {
            try {
                $botToken = env('TELEGRAM_BOT_TOKEN');
                $chatId = env('TELEGRAM_CHAT_ID');

                if ($botToken && $chatId) {
                    $message = "🔔 <b>New System Notification</b>\n";
                    $message .= "━━━━━━━━━━━━━━━━━━━\n";
                    $message .= "📝 <b>Title:</b> " . strip_tags($notification->title) . "\n";
                    if ($notification->click_url) {
                        $message .= "🔗 <b>Action:</b> <a href=\"" . $notification->click_url . "\">View Details</a>\n";
                    }
                    $message .= "━━━━━━━━━━━━━━━━━━━";

                    $url = "https://api.telegram.org/bot" . $botToken . "/sendMessage";
                    $data = [
                        'chat_id' => $chatId,
                        'text' => $message,
                        'parse_mode' => 'HTML',
                        'disable_web_page_preview' => true,
                    ];

                    $options = [
                        'http' => [
                            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                            'method'  => 'POST',
                            'content' => http_build_query($data),
                            'ssl' => [
                                'verify_peer' => false,
                                'verify_peer_name' => false,
                            ]
                        ]
                    ];

                    $context  = stream_context_create($options);
                    @file_get_contents($url, false, $context);
                }
            } catch (\Exception $e) {
                // Ensure no exception blocks the application flow
            }
        });
    }
}
