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
                // Check if this is an order notification and send detailed Telegram order alert
                if (str_contains(strtolower($notification->title), 'order')) {
                    $orderNumber = null;
                    if ($notification->click_url && preg_match('/search=([A-Za-z0-9_-]+)/', $notification->click_url, $m)) {
                        $orderNumber = $m[1];
                    } elseif (preg_match('/#([A-Za-z0-9_-]+)/', $notification->title, $m)) {
                        $orderNumber = $m[1];
                    }

                    if ($orderNumber) {
                        $order = \App\Models\Order::where('order_number', $orderNumber)->first();
                        if ($order) {
                            sendTelegramOrderNotification($order);
                            return;
                        }
                    }
                }

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
