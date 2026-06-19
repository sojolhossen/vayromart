<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Order;
use App\Models\Guest;
use App\Constants\Status;

class TelegramController extends Controller {

    public function webhook(Request $request) {
        try {
            $payload = $request->all();
            
            $chatId = $payload['message']['chat']['id'] ?? null;
            $text = trim($payload['message']['text'] ?? '');
            
            if (!$chatId || empty($text)) {
                return response('No message', 200);
            }

            // Security: Only allow configured admin Chat ID to query details
            if ($chatId != env('TELEGRAM_CHAT_ID')) {
                return response('Unauthorized', 200);
            }

            // Ignore start command
            if ($text === '/start') {
                $this->sendTelegramMessage($chatId, "👋 Hello Admin! Send me a customer name, username, email, or mobile number to search their details.");
                return response('OK', 200);
            }

            // Try to find matching Order first
            $orderQuery = Order::where('order_number', $text)
                ->orWhere('order_number', 'like', "%{$text}%");
                
            if (is_numeric($text) && strlen($text) < 8) {
                // If it's a short number, try to match padded order number (e.g. 12 -> OID-00012)
                $padded = 'OID-' . str_pad($text, 5, '0', STR_PAD_LEFT);
                $orderQuery->orWhere('order_number', $padded)->orWhere('id', $text);
            }
            
            $order = $orderQuery->first();

            if ($order) {
                $items = '';
                foreach ($order->orderDetail ?? [] as $detail) {
                    $productName = $detail->product->name ?? 'Product';
                    $items .= "• {$productName} (x{$detail->quantity}) - " . gs('cur_sym') . showAmount($detail->price * $detail->quantity, currencyFormat: false) . "\n";
                }
                
                $statusEmoji = '🟡';
                $statusText = 'Pending';
                if ($order->status == Status::ORDER_PENDING) {
                    $statusEmoji = '🟡';
                    $statusText = 'Pending';
                } elseif ($order->status == Status::ORDER_PROCESSING) {
                    $statusEmoji = '🔵';
                    $statusText = 'Processing';
                } elseif ($order->status == Status::ORDER_DISPATCHED) {
                    $statusEmoji = '🟣';
                    $statusText = 'Dispatched';
                } elseif ($order->status == Status::ORDER_DELIVERED) {
                    $statusEmoji = '🟢';
                    $statusText = 'Delivered';
                } elseif ($order->status == Status::ORDER_CANCELED) {
                    $statusEmoji = '🔴';
                    $statusText = 'Cancelled';
                } elseif ($order->status == Status::ORDER_RETURNED) {
                    $statusEmoji = '🟠';
                    $statusText = 'Returned';
                }
                
                $paymentStatus = $order->payment_status == Status::PAYMENT_SUCCESS ? '🟢 Paid' : '🔴 Not Paid';
                $paymentMethod = $order->is_cod ? 'Cash on Delivery (COD)' : 'Online Payment';
                
                $custName = '';
                $custPhone = '';
                $address = '';
                if ($order->shipping_address) {
                    $addr = $order->shipping_address;
                    $custName = ($addr->firstname ?? '') . ' ' . ($addr->lastname ?? '');
                    $custPhone = $addr->mobile ?? '';
                    $address = $addr->address ?? '';
                }
                
                if (empty($custName)) {
                    if ($order->user) {
                        $custName = $order->user->firstname . ' ' . $order->user->lastname;
                        $custPhone = $order->user->mobile;
                    } elseif ($order->guest) {
                        $custPhone = $order->guest->mobile;
                    }
                }
                
                $message = "📦 <b>Order Details: #{$order->order_number}</b>\n";
                $message .= "━━━━━━━━━━━━━━━━━━━\n";
                $message .= "• <b>Customer:</b> {$custName}\n";
                $message .= "• <b>Phone:</b> {$custPhone}\n";
                if ($address) {
                    $message .= "• <b>Address:</b> {$address}\n";
                }
                $message .= "• <b>Date:</b> " . showDateTime($order->created_at, 'd M Y h:i A') . "\n";
                $message .= "• <b>Status:</b> {$statusEmoji} {$statusText}\n";
                $message .= "• <b>Payment:</b> {$paymentStatus} ({$paymentMethod})\n";
                $message .= "━━━━━━━━━━━━━━━━━━━\n";
                $message .= "🛍️ <b>Items:</b>\n{$items}";
                $message .= "━━━━━━━━━━━━━━━━━━━\n";
                $message .= "• <b>Shipping Charge:</b> " . gs('cur_sym') . showAmount($order->shipping_charge, currencyFormat: false) . "\n";
                $message .= "• <b>Total Amount:</b> <b>" . gs('cur_sym') . showAmount($order->total_amount, currencyFormat: false) . " " . gs('cur_text') . "</b>\n";
                $message .= "━━━━━━━━━━━━━━━━━━━";
                
                $this->sendTelegramMessage($chatId, $message);
                return response('OK', 200);
            }

            // Search for Users
            $users = User::where('username', 'like', "%{$text}%")
                ->orWhere('email', 'like', "%{$text}%")
                ->orWhere('mobile', 'like', "%{$text}%")
                ->orWhere('firstname', 'like', "%{$text}%")
                ->orWhere('lastname', 'like', "%{$text}%")
                ->take(5)
                ->get();

            // Search for Guests (if no registered users are found or as fallback)
            $guests = collect();
            if ($users->isEmpty()) {
                $guests = Guest::where('email', 'like', "%{$text}%")
                    ->orWhere('mobile', 'like', "%{$text}%")
                    ->take(5)
                    ->get();
            }

            if ($users->isEmpty() && $guests->isEmpty()) {
                $this->sendTelegramMessage($chatId, "❌ No customer or order found matching: \"<b>{$text}</b>\"");
                return response('OK', 200);
            }

            // Single Registered User found
            if ($users->count() === 1) {
                $user = $users->first();
                $totalOrders = Order::where('user_id', $user->id)->count();
                $totalSpent = Order::where('user_id', $user->id)->where('payment_status', Status::PAYMENT_SUCCESS)->sum('total_amount');
                $statusText = $user->status == Status::USER_ACTIVE ? '🟢 Active' : '🔴 Banned';
                
                $message = "👤 <b>Registered Customer Details</b>\n";
                $message .= "━━━━━━━━━━━━━━━━━━━\n";
                $message .= "• <b>Name:</b> {$user->firstname} {$user->lastname}\n";
                $message .= "• <b>Username:</b> {$user->username}\n";
                $message .= "• <b>Phone:</b> +{$user->dial_code}{$user->mobile}\n";
                $message .= "• <b>Email:</b> {$user->email}\n";
                $message .= "• <b>Status:</b> {$statusText}\n";
                $message .= "• <b>Joined:</b> " . showDateTime($user->created_at, 'd M Y') . "\n";
                $message .= "• <b>Total Orders:</b> <b>{$totalOrders}</b>\n";
                $message .= "• <b>Total Spent:</b> <b>" . gs('cur_sym') . showAmount($totalSpent, currencyFormat: false) . " " . gs('cur_text') . "</b>\n";
                $message .= "━━━━━━━━━━━━━━━━━━━";
                
                $this->sendTelegramMessage($chatId, $message);
                return response('OK', 200);
            }

            // Single Guest found
            if ($guests->count() === 1) {
                $guest = $guests->first();
                $totalOrders = Order::where('guest_id', $guest->id)->count();
                $totalSpent = Order::where('guest_id', $guest->id)->where('payment_status', Status::PAYMENT_SUCCESS)->sum('total_amount');
                
                $message = "👤 <b>Guest Customer Details</b>\n";
                $message .= "━━━━━━━━━━━━━━━━━━━\n";
                $message .= "• <b>Phone:</b> +{$guest->dial_code}{$guest->mobile}\n";
                $message .= "• <b>Email:</b> {$guest->email}\n";
                $message .= "• <b>Country:</b> {$guest->country_name}\n";
                $message .= "• <b>First Visit:</b> " . showDateTime($guest->created_at, 'd M Y') . "\n";
                $message .= "• <b>Total Orders:</b> <b>{$totalOrders}</b>\n";
                $message .= "• <b>Total Spent:</b> <b>" . gs('cur_sym') . showAmount($totalSpent, currencyFormat: false) . " " . gs('cur_text') . "</b>\n";
                $message .= "━━━━━━━━━━━━━━━━━━━";
                
                $this->sendTelegramMessage($chatId, $message);
                return response('OK', 200);
            }

            // Multiple matches
            $message = "🔍 <b>Multiple Matches Found (Top 5):</b>\n";
            $message .= "━━━━━━━━━━━━━━━━━━━\n";
            $index = 1;
            foreach ($users as $user) {
                $message .= "{$index}. [Reg] <b>{$user->firstname} {$user->lastname}</b>\n";
                $message .= "   • Username: @{$user->username}\n";
                $message .= "   • Mobile: +{$user->dial_code}{$user->mobile}\n\n";
                $index++;
            }
            foreach ($guests as $guest) {
                $message .= "{$index}. [Guest] <b>+{$guest->dial_code}{$guest->mobile}</b>\n";
                $message .= "   • Email: {$guest->email}\n\n";
                $index++;
            }
            $message .= "━━━━━━━━━━━━━━━━━━━\n";
            $message .= "Please search with a more specific username or mobile number.";

            $this->sendTelegramMessage($chatId, $message);

        } catch (\Exception $e) {
            // Silence exceptions to keep Telegram webhook happy
        }

        return response('OK', 200);
    }

    public function setWebhook() {
        $botToken = env('TELEGRAM_BOT_TOKEN');
        if (!$botToken) {
            return "Please set TELEGRAM_BOT_TOKEN in .env first!";
        }
        
        $webhookUrl = route('telegram.webhook');
        
        if (str_contains($webhookUrl, 'localhost') || str_contains($webhookUrl, '127.0.0.1')) {
            return "<h3>Telegram Webhook Setup</h3>
                    <p>Current Webhook URL is: <code>{$webhookUrl}</code></p>
                    <p style='color:red;'><b>Error: Telegram cannot send webhooks to localhost!</b></p>
                    <p>To use this feature locally, you must use <b>ngrok</b> to expose your local port (e.g. <code>ngrok http 80</code>) and then set your <code>APP_URL</code> to the ngrok HTTPS link in your <code>.env</code> file.</p>
                    <p>If you have already uploaded the code to your live hosting server, make sure to visit this link on your live website domain (e.g. <code>https://yourdomain.com/telegram/set-webhook</code>).</p>";
        }
        
        $url = "https://api.telegram.org/bot" . $botToken . "/setWebhook?url=" . urlencode($webhookUrl);
        $result = @file_get_contents($url);
        
        if ($result) {
            $resObj = json_decode($result);
            if ($resObj && $resObj->ok) {
                return "<h3>Telegram Webhook Configured Successfully!</h3>
                        <p>Webhook URL set to: <code>{$webhookUrl}</code></p>
                        <p>Message: <i>{$resObj->description}</i></p>";
            }
        }
        
        return "Failed to set Telegram Webhook. Please check your bot token or network connection.";
    }

    private function sendTelegramMessage($chatId, $message) {
        $botToken = env('TELEGRAM_BOT_TOKEN');
        if (!$botToken) return;

        $url = "https://api.telegram.org/bot" . $botToken . "/sendMessage";
        $data = [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true
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
}
