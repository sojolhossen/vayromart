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
            $configuredChatId = trim(env('TELEGRAM_CHAT_ID'), '"\' ');
            if (strval($chatId) !== strval($configuredChatId)) {
                return response('Unauthorized', 200);
            }

            $lowerText = strtolower($text);

            // Handle cmd, /cmd, /help, help, /start commands
            if (in_array($lowerText, ['cmd', '/cmd', 'help', '/help', '/start'])) {
                $cmdMsg = "🤖 <b>Vayromart Telegram Bot Command Guide</b>\n";
                $cmdMsg .= "━━━━━━━━━━━━━━━━━━━\n\n";
                $cmdMsg .= "🔍 <b>1. Order & Customer Search:</b>\n";
                $cmdMsg .= "• Type Customer Mobile (e.g. <code>01712345678</code>) -> Lists all customer orders\n";
                $cmdMsg .= "• Type Order ID (e.g. <code>32</code> or <code>OID-00032</code>) -> Order details\n";
                $cmdMsg .= "• Type Name / Username (e.g. <code>john_doe</code>) -> Search customer\n\n";
                $cmdMsg .= "⚡ <b>2. Instant Order Status Update Commands:</b>\n";
                $cmdMsg .= "• <code>32-process</code> -> Mark #32 as 🔵 Processing\n";
                $cmdMsg .= "• <code>32-dispatch</code> -> Mark #32 as 🟣 Dispatched\n";
                $cmdMsg .= "• <code>32-deliver</code> -> Mark #32 as 🟢 Delivered\n";
                $cmdMsg .= "• <code>32-cancel</code> -> Mark #32 as 🔴 Canceled\n";
                $cmdMsg .= "• <code>32-return</code> -> Mark #32 as 🟠 Returned\n\n";
                $cmdMsg .= "🧾 <b>3. Invoice & Order Number Edit Commands:</b>\n";
                $cmdMsg .= "• <code>32-invoice</code> or <code>32-pdf</code> -> Get full Invoice details & web link\n";
                $cmdMsg .= "• <code>32-change OID-55555</code> or <code>32-number 99999</code> -> Change Order Number to new ID\n\n";
                $cmdMsg .= "📊 <b>4. Daily Sales & Stock Commands:</b>\n";
                $cmdMsg .= "• <code>today</code> or <code>report</code> -> View today's total sales & order breakdown\n";
                $cmdMsg .= "• <code>stock</code> or <code>lowstock</code> -> View products running low on stock\n\n";
                $cmdMsg .= "💡 <i>Tip: Replace '32' with any Order ID or Order Number!</i>\n";
                $cmdMsg .= "━━━━━━━━━━━━━━━━━━━";

                $this->sendTelegramMessage($chatId, $cmdMsg);
                return response('OK', 200);
            }

            // Check for Change Order Number Command (e.g. 32-change OID-55555, 32-number 99999, 32-rename 88888)
            if (preg_match('/^#?(OID-[0-9]+|[0-9]+)[\s\-_:=]+(change|rename|number)[\s\-_:=]+([A-Za-z0-9_-]+)$/i', $text, $m)) {
                $orderQueryStr = trim($m[1]);
                $newOrderNumber = trim($m[3]);

                $order = Order::where('order_number', $orderQueryStr)
                    ->orWhere('id', $orderQueryStr)
                    ->orWhere('order_number', 'LIKE', "%{$orderQueryStr}%")
                    ->first();

                if (!$order && is_numeric($orderQueryStr)) {
                    $padded = 'OID-' . str_pad($orderQueryStr, 5, '0', STR_PAD_LEFT);
                    $order = Order::where('order_number', $padded)->first();
                }

                if (!$order) {
                    $this->sendTelegramMessage($chatId, "❌ <b>Order Not Found!</b>\nCould not find any order matching \"<b>{$orderQueryStr}</b>\".");
                    return response('OK', 200);
                }

                // Check if new order number is already taken
                $exists = Order::where('order_number', $newOrderNumber)->where('id', '!=', $order->id)->exists();
                if ($exists) {
                    $this->sendTelegramMessage($chatId, "❌ <b>Error:</b> Order number <b>{$newOrderNumber}</b> is already taken by another order!");
                    return response('OK', 200);
                }

                $oldNumber = $order->order_number;
                $order->order_number = $newOrderNumber;
                $order->save();

                $replyMsg = "✅ <b>Order Number Updated Successfully!</b>\n";
                $replyMsg .= "━━━━━━━━━━━━━━━━━━━\n";
                $replyMsg .= "• <b>Old Order Number:</b> #{$oldNumber}\n";
                $replyMsg .= "• <b>New Order Number:</b> #<b>{$newOrderNumber}</b>\n";
                $replyMsg .= "• <b>Database ID:</b> {$order->id}\n";
                $replyMsg .= "━━━━━━━━━━━━━━━━━━━";

                $this->sendTelegramMessage($chatId, $replyMsg);
                return response('OK', 200);
            }

            // Check for Invoice Command (e.g. 32-invoice, 32-pdf, invoice 32)
            if (preg_match('/^#?(OID-[0-9]+|[0-9]+)[\s\-_:=]+(invoice|pdf)$/i', $text, $m) || preg_match('/^(invoice|pdf)[\s\-_:=]+#?(OID-[0-9]+|[0-9]+)$/i', $text, $m)) {
                $orderQueryStr = is_numeric($m[1]) || str_contains($m[1], 'OID-') ? trim($m[1]) : trim($m[2]);

                $order = Order::with(['orderDetail.product', 'user', 'guest'])
                    ->where('order_number', $orderQueryStr)
                    ->orWhere('id', $orderQueryStr)
                    ->orWhere('order_number', 'LIKE', "%{$orderQueryStr}%")
                    ->first();

                if (!$order && is_numeric($orderQueryStr)) {
                    $padded = 'OID-' . str_pad($orderQueryStr, 5, '0', STR_PAD_LEFT);
                    $order = Order::with(['orderDetail.product', 'user', 'guest'])->where('order_number', $padded)->first();
                }

                if (!$order) {
                    $this->sendTelegramMessage($chatId, "❌ <b>Order Not Found!</b>\nCould not find any order matching \"<b>{$orderQueryStr}</b>\".");
                    return response('OK', 200);
                }

                $custName = '';
                $custPhone = '';
                $address = '';
                if ($order->shipping_address) {
                    $addr = is_string($order->shipping_address) ? json_decode($order->shipping_address) : $order->shipping_address;
                    $custName = trim(($addr->firstname ?? '') . ' ' . ($addr->lastname ?? ''));
                    $custPhone = $addr->mobile ?? ($addr->phone ?? '');
                    $address = $addr->address ?? '';
                }

                if (empty($custName)) {
                    if ($order->user) {
                        $custName = trim($order->user->firstname . ' ' . $order->user->lastname);
                        $custPhone = $order->user->mobile;
                    } elseif ($order->guest) {
                        $custPhone = $order->guest->mobile;
                    }
                }

                $itemsList = "";
                $subtotal = 0;
                foreach ($order->orderDetail ?? [] as $idx => $detail) {
                    $pName = $detail->product->name ?? 'Product';
                    $itemTotal = $detail->price * $detail->quantity;
                    $subtotal += $itemTotal;
                    $num = $idx + 1;
                    $itemsList .= "{$num}. <b>{$pName}</b>\n";
                    $itemsList .= "   • Qty: {$detail->quantity} x " . gs('cur_sym') . showAmount($detail->price, currencyFormat: false) . " = <b>" . gs('cur_sym') . showAmount($itemTotal, currencyFormat: false) . "</b>\n";
                }

                $payMethod = $order->is_cod ? 'Cash on Delivery (COD)' : 'Online Paid';

                $invMsg = "🧾 <b>OFFICIAL INVOICE</b>\n";
                $invMsg .= "━━━━━━━━━━━━━━━━━━━\n";
                $invMsg .= "🏢 <b>Store:</b> " . gs('site_name') . "\n";
                $invMsg .= "📦 <b>Invoice #:</b> {$order->order_number}\n";
                $invMsg .= "📅 <b>Date:</b> " . showDateTime($order->created_at, 'd M Y h:i A') . "\n";
                $invMsg .= "💳 <b>Payment Method:</b> {$payMethod}\n";
                $invMsg .= "━━━━━━━━━━━━━━━━━━━\n";
                $invMsg .= "👤 <b>CUSTOMER DETAILS:</b>\n";
                $invMsg .= "• <b>Name:</b> {$custName}\n";
                $invMsg .= "• <b>Phone:</b> {$custPhone}\n";
                if ($address) {
                    $invMsg .= "• <b>Address:</b> {$address}\n";
                }
                $invMsg .= "━━━━━━━━━━━━━━━━━━━\n";
                $invMsg .= "🛍️ <b>ORDER ITEMS:</b>\n{$itemsList}";
                $invMsg .= "━━━━━━━━━━━━━━━━━━━\n";
                $invMsg .= "• <b>Subtotal:</b> " . gs('cur_sym') . showAmount($subtotal, currencyFormat: false) . "\n";
                $invMsg .= "• <b>Shipping Charge:</b> " . gs('cur_sym') . showAmount($order->shipping_charge, currencyFormat: false) . "\n";
                $invMsg .= "• <b>TOTAL PAYABLE:</b> <b>" . gs('cur_sym') . showAmount($order->total_amount, currencyFormat: false) . " " . gs('cur_text') . "</b>\n";
                $invMsg .= "━━━━━━━━━━━━━━━━━━━\n";
                $invMsg .= "🔗 <b>Web Invoice Link:</b>\n" . route('admin.print.invoice', $order->id);

                $this->sendTelegramMessage($chatId, $invMsg);
                return response('OK', 200);
            }

            // Handle Today Sales Report command (today / report / sales)
            if (in_array($lowerText, ['today', 'report', 'sales', '/today', '/report'])) {
                $todayOrders = Order::isValidOrder()->whereDate('created_at', now()->today())->get();
                $totalCount = $todayOrders->count();
                $totalSum = $todayOrders->where('status', '!=', Status::ORDER_CANCELED)->sum('total_amount');
                $pendingCount = $todayOrders->where('status', Status::ORDER_PENDING)->count();
                $processingCount = $todayOrders->where('status', Status::ORDER_PROCESSING)->count();
                $dispatchedCount = $todayOrders->where('status', Status::ORDER_DISPATCHED)->count();
                $deliveredCount = $todayOrders->where('status', Status::ORDER_DELIVERED)->count();
                $canceledCount = $todayOrders->where('status', Status::ORDER_CANCELED)->count();

                $reportMsg = "📊 <b>Today's Sales & Order Summary (" . date('d M Y') . ")</b>\n";
                $reportMsg .= "━━━━━━━━━━━━━━━━━━━\n";
                $reportMsg .= "• <b>Total Orders Today:</b> <b>{$totalCount}</b>\n";
                $reportMsg .= "• <b>Total Sales Today:</b> <b>" . gs('cur_sym') . showAmount($totalSum, currencyFormat: false) . " " . gs('cur_text') . "</b>\n";
                $reportMsg .= "━━━━━━━━━━━━━━━━━━━\n";
                $reportMsg .= "🟡 <b>Pending:</b> {$pendingCount}\n";
                $reportMsg .= "🔵 <b>Processing:</b> {$processingCount}\n";
                $reportMsg .= "🟣 <b>Dispatched:</b> {$dispatchedCount}\n";
                $reportMsg .= "🟢 <b>Delivered:</b> {$deliveredCount}\n";
                $reportMsg .= "🔴 <b>Canceled:</b> {$canceledCount}\n";
                $reportMsg .= "━━━━━━━━━━━━━━━━━━━";

                $this->sendTelegramMessage($chatId, $reportMsg);
                return response('OK', 200);
            }

            // Handle Low Stock Inventory command (stock / lowstock)
            if (in_array($lowerText, ['stock', 'lowstock', 'inventory', '/stock', '/lowstock'])) {
                $lowStockProducts = \App\Models\Product::where('track_inventory', Status::YES)
                    ->where('in_stock', '<=', 5)
                    ->take(10)
                    ->get();

                if ($lowStockProducts->isEmpty()) {
                    $this->sendTelegramMessage($chatId, "✅ <b>Inventory Status:</b>\nAll products have sufficient stock levels!");
                    return response('OK', 200);
                }

                $stockMsg = "⚠️ <b>Low Stock Inventory Alert (Top 10)</b>\n";
                $stockMsg .= "━━━━━━━━━━━━━━━━━━━\n";
                foreach ($lowStockProducts as $p) {
                    $stockStatus = $p->in_stock == 0 ? "🔴 OUT OF STOCK" : "🟡 {$p->in_stock} left";
                    $stockMsg .= "• <b>{$p->name}</b>\n  Status: {$stockStatus}\n";
                }
                $stockMsg .= "━━━━━━━━━━━━━━━━━━━";

                $this->sendTelegramMessage($chatId, $stockMsg);
                return response('OK', 200);
            }

            // Check if message is an Order Status Update Command (e.g. 32-cancel, 32-process, 32-dispatch, 32-deliver, 32-return)
            if (preg_match('/^#?(OID-[0-9]+|[0-9]+)[\s\-_:=]+(process|proccess|processing|dispatch|dispach|dispatched|deliver|delivered|cancel|cancle|canceled|cancelled|return|returned)$/i', $text, $matches)) {
                $orderQueryStr = trim($matches[1]);
                $actionStr = strtolower(trim($matches[2]));
                
                $targetStatus = null;
                $actionTitle = '';
                $statusEmoji = '';
                
                if (in_array($actionStr, ['process', 'proccess', 'processing'])) {
                    $targetStatus = Status::ORDER_PROCESSING;
                    $actionTitle = 'PROCESSING';
                    $statusEmoji = '🔵';
                } elseif (in_array($actionStr, ['dispatch', 'dispach', 'dispatched'])) {
                    $targetStatus = Status::ORDER_DISPATCHED;
                    $actionTitle = 'DISPATCHED';
                    $statusEmoji = '🟣';
                } elseif (in_array($actionStr, ['deliver', 'delivered'])) {
                    $targetStatus = Status::ORDER_DELIVERED;
                    $actionTitle = 'DELIVERED';
                    $statusEmoji = '🟢';
                } elseif (in_array($actionStr, ['cancel', 'cancle', 'canceled', 'cancelled'])) {
                    $targetStatus = Status::ORDER_CANCELED;
                    $actionTitle = 'CANCELED';
                    $statusEmoji = '🔴';
                } elseif (in_array($actionStr, ['return', 'returned'])) {
                    $targetStatus = Status::ORDER_RETURNED;
                    $actionTitle = 'RETURNED';
                    $statusEmoji = '🟠';
                }

                if ($targetStatus !== null) {
                    $order = Order::where('order_number', $orderQueryStr)
                        ->orWhere('id', $orderQueryStr)
                        ->orWhere('order_number', 'LIKE', "%{$orderQueryStr}%")
                        ->first();

                    if (!$order && is_numeric($orderQueryStr)) {
                        $padded = 'OID-' . str_pad($orderQueryStr, 5, '0', STR_PAD_LEFT);
                        $order = Order::where('order_number', $padded)->first();
                    }

                    if (!$order) {
                        $this->sendTelegramMessage($chatId, "❌ <b>Order Not Found!</b>\nCould not find any order matching \"<b>{$orderQueryStr}</b>\".");
                        return response('OK', 200);
                    }

                    $order->status = $targetStatus;
                    if ($targetStatus == Status::ORDER_DELIVERED && $order->is_cod) {
                        $order->payment_status = Status::PAYMENT_SUCCESS;
                        if ($order->deposit) {
                            $order->deposit->status = Status::PAYMENT_SUCCESS;
                            $order->deposit->save();
                        }
                    }
                    $order->save();

                    $custPhone = $order->shipping_address->mobile ?? ($order->user->mobile ?? ($order->guest->mobile ?? 'N/A'));

                    $replyMsg = "✅ <b>Order Status Updated Successfully!</b>\n";
                    $replyMsg .= "━━━━━━━━━━━━━━━━━━━\n";
                    $replyMsg .= "• <b>Order ID:</b> #{$order->order_number}\n";
                    $replyMsg .= "• <b>Customer Phone:</b> {$custPhone}\n";
                    $replyMsg .= "• <b>New Status:</b> {$statusEmoji} <b>{$actionTitle}</b>\n";
                    $replyMsg .= "• <b>Total Amount:</b> " . gs('cur_sym') . showAmount($order->total_amount, currencyFormat: false) . " " . gs('cur_text') . "\n";
                    $replyMsg .= "━━━━━━━━━━━━━━━━━━━";

                    $this->sendTelegramMessage($chatId, $replyMsg);
                    return response('OK', 200);
                }
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
                $message .= "━━━━━━━━━━━━━━━━━━━\n";
                $message .= "⚡ <b>Quick Update Status:</b>\n";
                $message .= "<code>{$order->id}-process</code> | <code>{$order->id}-dispatch</code> | <code>{$order->id}-deliver</code> | <code>{$order->id}-cancel</code>\n";
                $message .= "━━━━━━━━━━━━━━━━━━━";
                
                $this->sendTelegramMessage($chatId, $message);
                return response('OK', 200);
            }

            // Search orders matching customer phone number or query
            $cleanPhone = preg_replace('/[^0-9]/', '', $text);
            $customerOrdersQuery = Order::with(['orderDetail.product', 'user', 'guest'])
                ->where(function($q) use ($text, $cleanPhone) {
                    $q->where('shipping_address', 'like', "%{$text}%");
                    if (strlen($cleanPhone) >= 3) {
                        $q->orWhere('shipping_address', 'like', "%{$cleanPhone}%");
                    }
                    $q->orWhereHas('user', function($u) use ($text, $cleanPhone) {
                        $u->where('mobile', 'like', "%{$text}%")
                          ->orWhere('username', 'like', "%{$text}%");
                        if (strlen($cleanPhone) >= 3) {
                            $u->orWhere('mobile', 'like', "%{$cleanPhone}%");
                        }
                    });
                    $q->orWhereHas('guest', function($g) use ($text, $cleanPhone) {
                        $g->where('mobile', 'like', "%{$text}%")
                          ->orWhere('email', 'like', "%{$text}%");
                        if (strlen($cleanPhone) >= 3) {
                            $g->orWhere('mobile', 'like', "%{$cleanPhone}%");
                        }
                    });
                })
                ->orderBy('id', 'desc');

            $matchedOrders = $customerOrdersQuery->take(5)->get();

            if ($matchedOrders->count() > 0) {
                $count = $matchedOrders->count();
                $message = "👤 <b>Customer Orders Found ({$count}) for: {$text}</b>\n";
                $message .= "━━━━━━━━━━━━━━━━━━━\n\n";

                foreach ($matchedOrders as $idx => $ord) {
                    $items = '';
                    foreach ($ord->orderDetail ?? [] as $detail) {
                        $pName = $detail->product->name ?? 'Product';
                        $items .= "  • {$pName} (x{$detail->quantity})\n";
                    }

                    $sEmoji = '🟡';
                    $sText = 'Pending';
                    if ($ord->status == Status::ORDER_PENDING) { $sEmoji = '🟡'; $sText = 'Pending'; }
                    elseif ($ord->status == Status::ORDER_PROCESSING) { $sEmoji = '🔵'; $sText = 'Processing'; }
                    elseif ($ord->status == Status::ORDER_DISPATCHED) { $sEmoji = '🟣'; $sText = 'Dispatched'; }
                    elseif ($ord->status == Status::ORDER_DELIVERED) { $sEmoji = '🟢'; $sText = 'Delivered'; }
                    elseif ($ord->status == Status::ORDER_CANCELED) { $sEmoji = '🔴'; $sText = 'Cancelled'; }
                    elseif ($ord->status == Status::ORDER_RETURNED) { $sEmoji = '🟠'; $sText = 'Returned'; }

                    $orderIdNum = $ord->id;
                    $orderNumStr = $ord->order_number;
                    $totalFormatted = gs('cur_sym') . showAmount($ord->total_amount, currencyFormat: false) . " " . gs('cur_text');

                    $message .= "📦 <b>Order #{$orderNumStr} (ID: {$orderIdNum})</b>\n";
                    $message .= "• <b>Date:</b> " . showDateTime($ord->created_at, 'd M Y h:i A') . "\n";
                    $message .= "• <b>Status:</b> {$sEmoji} <b>{$sText}</b>\n";
                    if ($items) {
                        $message .= "• <b>Items:</b>\n{$items}";
                    }
                    $message .= "• <b>Total:</b> <b>{$totalFormatted}</b>\n";
                    $message .= "⚡ <b>Update Command:</b>\n";
                    $message .= "<code>{$orderIdNum}-process</code> | <code>{$orderIdNum}-dispatch</code> | <code>{$orderIdNum}-cancel</code>\n\n";
                }

                $message .= "━━━━━━━━━━━━━━━━━━━\n";
                $message .= "💡 <i>Tap any status command above to copy and send instantly!</i>";

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
            try {
                $errorMsg = "❌ <b>Telegram Webhook Error</b>\n";
                $errorMsg .= "• Message: " . $e->getMessage() . "\n";
                $errorMsg .= "• File: " . basename($e->getFile()) . "\n";
                $errorMsg .= "• Line: " . $e->getLine();
                $this->sendTelegramMessage($chatId ?? env('TELEGRAM_CHAT_ID'), $errorMsg);
            } catch (\Exception $e2) {
                // Squelch
            }
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
